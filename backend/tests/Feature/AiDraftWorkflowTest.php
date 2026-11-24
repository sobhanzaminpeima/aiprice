<?php
namespace Tests\Feature;

use App\Models\{User,Listing,VehicleBrand,VehicleModel};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage,Queue,Notification};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AiDraftWorkflowTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void {
        parent::setUp();$this->seed(DatabaseSeeder::class);Storage::fake('public');Queue::fake();Notification::fake();
        config(['services.ai_provider'=>'runcrate','services.runcrate.key'=>'fixture-key-not-real','services.runcrate.base_url'=>'https://api.runcrate.ai/v1','services.runcrate.model'=>'Qwen/Qwen3.8-Flash']);
        Http::preventStrayRequests();
    }
    private function draft():array {
        return ['type'=>'property','title'=>'Studio in Girne','description'=>'A furnished studio in Girne with a pool.','seo_title'=>'Studio in Girne for sale','seo_description'=>'Explore this furnished studio in Girne. Contact AIPrice for further details.',
            'property_type'=>'Apartment','bedrooms'=>0,'bathrooms'=>1,'price'=>95000,'currency'=>'GBP','city'=>'Girne','has_pool'=>true];
    }
    public function test_ai_draft_requires_auth_and_does_not_save_or_publish():void {
        $this->postJson('/api/v1/listings/extract',['text'=>'A studio in Girne for GBP 95000'])->assertUnauthorized();
        $seller=User::factory()->create();$before=Listing::count();Http::fake(['api.runcrate.ai/*'=>Http::response(['choices'=>[['message'=>['content'=>json_encode($this->draft())]]]])]);
        $res=$this->actingAs($seller,'sanctum')->postJson('/api/v1/listings/extract',['text'=>'Furnished studio in Girne, pool, GBP 95000.']);
        $res->assertOk()->assertJsonPath('extracted.bedrooms',0)->assertJsonPath('extracted.has_pool',true)->assertJsonPath('extracted.review_required',true);
        $this->assertSame($before,Listing::count());Http::assertSent(fn($r)=>$r->url()==='https://api.runcrate.ai/v1/chat/completions'&&$r->hasHeader('Authorization','Bearer fixture-key-not-real'));
    }
    public function test_provider_failure_is_honest_and_does_not_expose_key_or_response():void {
        Http::fake(['api.runcrate.ai/*'=>Http::response(['error'=>['message'=>'private provider details fixture-key-not-real']],529)]);
        $response=$this->actingAs(User::factory()->create(),'sanctum')->postJson('/api/v1/listings/extract',['text'=>'A studio in Girne for GBP 95000.']);
        $response->assertUnprocessable();$this->assertStringContainsString('overloaded',$response->json('message'));$this->assertStringNotContainsString('fixture-key',$response->getContent());
    }
    public function test_untrusted_model_fields_and_bad_json_cannot_control_owner_or_publication():void {
        $draft=$this->draft()+['user_id'=>'bad-owner','status'=>'active','is_featured'=>true,'api_key'=>'secret'];
        Http::fake(['api.runcrate.ai/*'=>Http::sequence()->push(['choices'=>[['message'=>['content'=>json_encode($draft)]]]])->push(['choices'=>[['message'=>['content'=>'not json']]]])]);
        $response=$this->actingAs(User::factory()->create(),'sanctum')->postJson('/api/v1/listings/extract',['text'=>'Furnished studio, pool, GBP 95000 in Girne.']);
        $response->assertOk()->assertJsonMissingPath('extracted.status')->assertJsonMissingPath('extracted.user_id')->assertJsonMissingPath('extracted.api_key');
        $this->postJson('/api/v1/listings/extract',['text'=>'Furnished studio, pool, GBP 95000 in Girne.'])->assertUnprocessable();
    }
    public function test_seller_fields_seo_and_all_photos_are_saved_together():void {
        $seller=User::factory()->create(['role'=>'seller']);$seller->assignRole('seller');
        $response=$this->actingAs($seller,'sanctum')->post('/api/v1/listings/create',[
            'type'=>'property','title'=>'Studio in Girne','description'=>'Furnished studio','property_type_name'=>'Apartment','price'=>95000,'currency'=>'GBP','bedrooms'=>0,'bathrooms'=>1,'area_sqm'=>45,'floor_number'=>2,'furnishing'=>'furnished','has_pool'=>true,
            'seo_title'=>'Studio in Girne','seo_description'=>'A studio in Girne with a shared pool.','status'=>'active','user_id'=>'injected',
            'images'=>[UploadedFile::fake()->image('first.jpg'),UploadedFile::fake()->image('second.png')],
        ],['Accept'=>'application/json']);
        $response->assertCreated()->assertJsonPath('listing.status','pending')->assertJsonPath('listing.user_id',$seller->id);
        $listing=Listing::findOrFail($response->json('listing.id'));$this->assertCount(2,$listing->images);$this->assertSame($listing->images[0],$listing->thumbnail);$this->assertSame('Studio in Girne',$listing->meta['seo']['meta_title']);
        $this->assertSame(0,(int)$listing->listable->bedrooms);$this->assertTrue($listing->listable->has_pool);$this->assertSame('furnished',$listing->listable->furnishing);
        $this->assertSame(2,(int)$listing->listable->floor_number);
        $this->post('/api/v1/listings/create',['type'=>'property','title'=>'Invalid file','property_type_name'=>'Apartment','price'=>1,'images'=>[UploadedFile::fake()->create('payload.svg',5,'image/svg+xml')]],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertDatabaseMissing('listings',['title'=>'Invalid file']);
    }
    public function test_admin_creation_keeps_car_fields_and_rejects_cross_brand_models():void {
        $admin=User::where('role','super_admin')->firstOrFail();$brand=VehicleBrand::where('name','Toyota')->firstOrFail();$model=VehicleModel::where('brand_id',$brand->id)->firstOrFail();
        $payload=['user_id'=>$admin->id,'type'=>'vehicle','title'=>'Vehicle test','price'=>18000,'currency'=>'GBP','status'=>'pending','brand_id'=>$brand->id,'model_id'=>$model->id,'year'=>2021,'mileage'=>0,'color'=>'Silver','body_type'=>'Sedan','engine_size'=>'1.8','seo_title'=>'Test vehicle SEO'];
        $response=$this->actingAs($admin,'sanctum')->postJson('/api/v1/admin/listings',$payload)->assertCreated();
        $listing=Listing::findOrFail($response->json('listing.id'));$this->assertSame(0,(int)$listing->listable->mileage);$this->assertSame('Silver',$listing->listable->color);
        $foreign=VehicleModel::where('brand_id','!=',$brand->id)->firstOrFail();
        $this->postJson('/api/v1/admin/listings',array_replace($payload,['model_id'=>$foreign->id]))->assertUnprocessable();
        $this->putJson('/api/v1/admin/listings/'.$listing->id,['price'=>-5])->assertUnprocessable();
    }
    public function test_owner_updates_reenter_moderation_and_admin_api_is_protected():void {
        $seller=User::factory()->create(['role'=>'seller']);$seller->assignRole('seller');
        $this->actingAs($seller,'sanctum')->postJson('/api/v1/admin/listings/extract',['text'=>'A furnished studio in Girne at GBP 95000'])->assertForbidden();
        $property=\App\Models\Property::create(['property_type_id'=>\App\Models\PropertyType::firstOrFail()->id,'title'=>'Before edit']);
        $listing=Listing::create(['user_id'=>$seller->id,'listable_type'=>get_class($property),'listable_id'=>$property->id,'title'=>'Listing before edit','type'=>'property','price'=>1,'currency'=>'GBP','status'=>'active']);
        $this->putJson('/api/v1/listings/'.$listing->id,['title'=>'Edited listing','price'=>2,'seo_title'=>'Edited title'])->assertOk()->assertJsonPath('listing.status','pending');
        $other=User::factory()->create();$this->actingAs($other,'sanctum')->putJson('/api/v1/listings/'.$listing->id,['title'=>'Unauthorized'])->assertNotFound();
    }

    public function test_local_phone_numbers_are_removed_from_ai_marketing_text():void {
        $draft=$this->draft();
        $draft['description']='Studio for GBP 95000. Call 0533 858 33 14 or +905338583314.';
        Http::fake(['api.runcrate.ai/*'=>Http::response(['choices'=>[['message'=>['content'=>json_encode($draft)]]]])]);
        $response=$this->actingAs(User::factory()->create(),'sanctum')->postJson('/api/v1/listings/extract',['text'=>'Studio in Girne for GBP 95000, call 0533 858 33 14.'])->assertOk();
        $this->assertStringNotContainsString('0533',$response->json('extracted.description'));
        $this->assertStringNotContainsString('+90533',$response->json('extracted.description'));
        $this->assertStringContainsString('95000',$response->json('extracted.description'));
    }

    public function test_model_only_update_rejects_a_model_from_another_brand():void {
        $admin=User::where('role','super_admin')->firstOrFail();
        $brand=VehicleBrand::where('name','Toyota')->firstOrFail();
        $model=VehicleModel::where('brand_id',$brand->id)->firstOrFail();
        $response=$this->actingAs($admin,'sanctum')->postJson('/api/v1/admin/listings',[
            'user_id'=>$admin->id,'type'=>'vehicle','title'=>'Model integrity test','price'=>10000,
            'brand_id'=>$brand->id,'model_id'=>$model->id,'year'=>2021,
        ])->assertCreated();
        $foreign=VehicleModel::where('brand_id','!=',$brand->id)->firstOrFail();
        $this->putJson('/api/v1/admin/listings/'.$response->json('listing.id'),['model_id'=>$foreign->id])->assertUnprocessable();
        $this->assertSame($model->id,Listing::findOrFail($response->json('listing.id'))->listable->model_id);
    }
}
