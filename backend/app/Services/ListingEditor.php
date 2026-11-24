<?php

namespace App\Services;

use App\Models\{Listing,Vehicle,VehicleBrand,VehicleModel,Property,PropertyType,Rental,PriceHistory};
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** One validated persistence path for seller and administrator listing forms. */
class ListingEditor
{
    public function save(Request $request, bool $admin = false, ?Listing $listing = null): Listing
    {
        $type = $listing?->type ?? $request->input('type');
        $rules = [
            'type'=>'required|in:vehicle,property,rental','title'=>'required|string|max:255',
            'description'=>'nullable|string|max:20000','price'=>'required|numeric|min:0|max:1000000000',
            'currency'=>'sometimes|in:GBP,USD,EUR,TRY','city'=>'nullable|string|max:100',
            'district'=>'nullable|string|max:100','country'=>'nullable|string|max:3','address'=>'nullable|string|max:500',
            'price_negotiable'=>'sometimes|boolean','video_url'=>'nullable|url|max:2000',
            'brand_name'=>'nullable|string|max:100','model_name'=>'nullable|string|max:100',
            'property_type_name'=>'nullable|string|max:100',
            'contact_phone'=>'nullable|string|max:40|regex:/^\+?[0-9 ()-]{6,40}$/',
            'brand_id'=>'nullable|uuid|exists:vehicle_brands,id','model_id'=>'nullable|uuid|exists:vehicle_models,id',
            'property_type_id'=>'nullable|uuid|exists:property_types,id',
            'year'=>'nullable|integer|min:1950|max:'.(date('Y')+2),'mileage'=>'nullable|integer|min:0|max:5000000',
            'fuel_type'=>'nullable|string|max:40','transmission'=>'nullable|string|max:40','body_type'=>'nullable|string|max:60',
            'color'=>'nullable|string|max:60','engine_size'=>'nullable|string|max:60','condition'=>'nullable|string|max:100',
            'bedrooms'=>'nullable|integer|min:0|max:100','bathrooms'=>'nullable|integer|min:0|max:100',
            'area_sqm'=>'nullable|numeric|min:0|max:10000000','build_year'=>'nullable|integer|min:1800|max:'.(date('Y')+3),
            'floor_number'=>'nullable|integer|min:-10|max:200','furnishing'=>'nullable|string|max:60',
            'has_parking'=>'sometimes|boolean','has_garden'=>'sometimes|boolean','has_pool'=>'sometimes|boolean','has_elevator'=>'sometimes|boolean',
            'rental_type'=>'nullable|in:daily,weekly,monthly,yearly','seo_title'=>'nullable|string|max:70','seo_description'=>'nullable|string|max:160',
            'images'=>'sometimes|array|max:20','images.*'=>'required|file|mimes:jpg,jpeg,png,webp|max:10240|dimensions:max_width=12000,max_height=12000',
        ];
        if ($listing) {
            foreach (['type','title','price'] as $field) $rules[$field]=str_replace('required|','sometimes|',$rules[$field]);
        }
        if ($admin) $rules += ['user_id'=>($listing?'sometimes':'required').'|uuid|exists:users,id','status'=>'sometimes|in:draft,pending,active,rejected,sold,expired','is_featured'=>'sometimes|boolean','is_premium'=>'sometimes|boolean'];
        if($listing && $request->has('type') && $request->input('type')!==$listing->type)throw ValidationException::withMessages(['type'=>'The listing type cannot be changed.']);
        $data=$request->validate($rules);
        $paths=[];
        try {
            return DB::transaction(function () use ($request,$admin,$listing,$type,$data,&$paths) {
                $traits=Arr::only($data,['year','mileage','fuel_type','transmission','body_type','color','engine_size','condition','bedrooms','bathrooms','area_sqm','build_year','floor_number','furnishing','has_parking','has_garden','has_pool','has_elevator','rental_type']);
                if ($type==='vehicle' && (!$listing || isset($data['brand_id']) || isset($data['brand_name']) || isset($data['model_id']) || isset($data['model_name']))) {
                    $brand=isset($data['brand_id'])?VehicleBrand::findOrFail($data['brand_id']):(!empty($data['brand_name'])?VehicleBrand::firstOrCreate(['name'=>$data['brand_name']],['is_active'=>true]):$listing?->listable?->brand);
                    $model=isset($data['model_id'])?VehicleModel::findOrFail($data['model_id']):($brand&&!empty($data['model_name'])?VehicleModel::firstOrCreate(['brand_id'=>$brand->id,'name'=>$data['model_name']],['is_active'=>true]):$listing?->listable?->model);
                    if (!$brand||!$model||$model->brand_id!==$brand->id) throw ValidationException::withMessages(['model_id'=>'Choose a model belonging to the selected brand.']);
                    if (!$listing && empty($data['year'])) throw ValidationException::withMessages(['year'=>'Vehicle year is required.']);
                    $traits+=['brand_id'=>$brand->id,'model_id'=>$model->id];
                }
                if ($type==='property' && (!$listing || isset($data['property_type_id']) || isset($data['property_type_name']))) {
                    $propertyType=isset($data['property_type_id'])?PropertyType::findOrFail($data['property_type_id']):(!empty($data['property_type_name'])?PropertyType::firstOrCreate(['name'=>$data['property_type_name']],['is_active'=>true]):null);
                    if (!$propertyType) throw ValidationException::withMessages(['property_type_name'=>'Property type is required.']);
                    $traits['property_type_id']=$propertyType->id;
                }
                if ($type!=='vehicle') $traits['title']=$data['title']??$listing?->title;
                $listable=$listing?->listable;
                if (!$listing) $listable=match($type){'vehicle'=>Vehicle::create($traits),'property'=>Property::create($traits),'rental'=>Rental::create($traits+['rental_type'=>'monthly'])};
                elseif ($listable) $listable->update($traits);
                $core=Arr::only($data,['title','description','price','currency','city','district','country','address','price_negotiable','video_url']);
                foreach (['title','description','seo_title','seo_description'] as $field) if(isset($core[$field]))$core[$field]=strip_tags($core[$field]);
                if ($admin) $core+=Arr::only($data,['status','is_featured','is_premium']);
                if (!$listing) $core+=['user_id'=>$admin?$data['user_id']:$request->user()->id,'type'=>$type,'listable_type'=>get_class($listable),'listable_id'=>$listable->id,'status'=>$admin?($data['status']??'pending'):'pending','currency'=>$data['currency']??'GBP'];
                elseif (!$admin) $core['status']='pending';
                if (($core['status']??null)==='active') $core['published_at']=$listing?->published_at??now();
                $meta=$listing?->meta??[];
                if (array_key_exists('contact_phone', $data)) {
                    $meta['contact_phone'] = trim($data['contact_phone'] ?? '');
                }
                foreach (['seo_title'=>'meta_title','seo_description'=>'meta_description'] as $input=>$output) if(array_key_exists($input,$data))$meta['seo'][$output]=strip_tags($data[$input]??'');
                $core['meta']=$meta;
                $previousPrice=$listing?->price;
                if ($listing) $listing->update($core);else $listing=Listing::create($core);
                $images=$listing->images??[];
                if (count($images)+count($request->file('images',[]))>20) throw ValidationException::withMessages(['images'=>'A listing can have at most 20 photos.']);
                foreach($request->file('images',[]) as $file){$path=$file->store('listings/'.$listing->id,'public');if(!$path)throw new \RuntimeException('Photo storage failed.');$paths[]=$path;$images[]='/storage/'.$path;}
                if ($paths) $listing->update(['images'=>$images,'thumbnail'=>$listing->thumbnail?:$images[0]]);
                if ($previousPrice===null || (float)$previousPrice!==(float)$listing->price) PriceHistory::create(['listing_id'=>$listing->id,'price'=>$listing->price,'currency'=>$listing->currency,'changed_by'=>$admin?'admin':'seller']);
                return $listing->fresh(['listable']);
            });
        } catch (\Throwable $error) {
            if ($paths) Storage::disk('public')->delete($paths);
            throw $error;
        }
    }
}
