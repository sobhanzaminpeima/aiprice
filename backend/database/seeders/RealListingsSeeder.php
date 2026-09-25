<?php
namespace Database\Seeders;

use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealListingsSeeder extends Seeder
{
    // Curated Unsplash photo IDs — permanent, freely embeddable
    private array $carImages = [
        'white-sedan'   => 'photo-1503376780353-7e6692767b70',
        'silver-golf'   => 'photo-1541899481282-d53bffe3c35d',
        'black-bmw'     => 'photo-1555215695-3004980ad54e',
        'white-merc'    => 'photo-1618843479619-f3d0d81e4d10',
        'blue-civic'    => 'photo-1486262715619-67b85e0b08d3',
        'grey-suv'      => 'photo-1519641471654-76ce0107ad1b',
        'red-tucson'    => 'photo-1494976388531-d1058494cdd8',
        'white-kia'     => 'photo-1490902931801-d6f80ca94fe4',
        'silver-ford'   => 'photo-1533473359331-0135ef1b58bf',
        'black-audi'    => 'photo-1542362567-b07e54358753',
        'white-rav4'    => 'photo-1519641471654-76ce0107ad1b',
        'grey-passat'   => 'photo-1502877338535-766e1452684a',
        'black-eclass'  => 'photo-1523983388277-336a66bf9bcd',
        'white-crv'     => 'photo-1471444936872-06e93a1a9b2d',
        'blue-x3'       => 'photo-1544636331-e26879cd4d9b',
        'red-megane'    => 'photo-1580273916550-e323be2ae537',
        'silver-oct'    => 'photo-1471943038886-fb8f0b4a7b71',
        'white-3008'    => 'photo-1604054923871-f1b7e3a20cef',
        'black-xc60'    => 'photo-1561714741-3d07ca6f4e7c',
        'red-cx5'       => 'photo-1552519507-da3b142c6e3d',
    ];

    private array $propImages = [
        'apt-modern'   => 'photo-1545324418-cc1a3fa10c00',
        'villa-pool'   => 'photo-1613977257363-707ba9348227',
        'apt-studio'   => 'photo-1560448204-e02f11c3d0e2',
        'villa-luxury' => 'photo-1564013799919-ab600027ffc6',
        'apt-seaview'  => 'photo-1580587771525-78b9dba3b914',
        'villa-big'    => 'photo-1512917774080-9991f1c4c750',
        'penthouse'    => 'photo-1600596542815-ffad4c1539a9',
        'apt-univ'     => 'photo-1574362848149-11496d93a7c7',
        'bungalow'     => 'photo-1558618666-fcd25c85cd64',
        'flat-ground'  => 'photo-1596436117842-c9d6b7f3c2d0',
        'villa-6bed'   => 'photo-1613977257592-6a256c7839f2',
        'apt-3bed'     => 'photo-1560185007-5f0bb1866cab',
        'offplan'      => 'photo-1486325212027-8081e485255e',
        'mtn-villa'    => 'photo-1600047509807-ba8f99d2cdde',
        'duplex'       => 'photo-1619880860373-27b5c07e9f5c',
        'studio-inv'   => 'photo-1522708323590-d24dbb6b0267',
        'townhouse'    => 'photo-1570129477492-45c003edd2be',
        'apt-complex'  => 'photo-1583608205776-bfd35f0d9f83',
        'villa-5bed'   => 'photo-1600047509258-6db18be0d3e0',
        'seaview-apt'  => 'photo-1484154218962-a197022b5858',
    ];

    private function carImg(string $key, int $w = 800): array {
        $id = $this->carImages[$key] ?? 'photo-1503376780353-7e6692767b70';
        $base = "https://images.unsplash.com/{$id}?w={$w}&q=80&auto=format&fit=crop";
        return [$base, str_replace("w={$w}", 'w=400', $base)];
    }

    private function propImg(string $key, int $w = 800): array {
        $id = $this->propImages[$key] ?? 'photo-1545324418-cc1a3fa10c00';
        $base = "https://images.unsplash.com/{$id}?w={$w}&q=80&auto=format&fit=crop";
        return [$base, str_replace("w={$w}", 'w=400', $base)];
    }

    public function run(): void
    {
        $seller = User::where('role', 'dealer')->first()
            ?? User::where('role', 'seller')->first()
            ?? User::where('email', 'admin@aiprice.app')->first();
        if (!$seller) return;

        UserProfile::firstOrCreate(['user_id' => $seller->id], [
            'business_name' => 'AIPrice Motors',
            'country' => 'CY',
            'city' => 'Lefkosa',
        ]);

        // Clear old seeded listings
        Listing::whereIn('city', ['Lefkosa','Girne','Gazimağusa','Iskele','Guzelyurt'])->forceDelete();

        $this->seedCars($seller);
        $this->seedProperties($seller);
    }

    private function seedCars(User $seller): void
    {
        $cars = [
            ['brand'=>'Toyota','model'=>'Corolla','year'=>2019,'price'=>285000,'currency'=>'TRY','mileage'=>42000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Lefkosa','color'=>'White','imgKey'=>'white-sedan','body'=>'sedan','engine'=>'1.6','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Volkswagen','model'=>'Golf','year'=>2020,'price'=>340000,'currency'=>'TRY','mileage'=>28000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Girne','color'=>'Silver','imgKey'=>'silver-golf','body'=>'hatchback','engine'=>'1.4','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'BMW','model'=>'3 Series','year'=>2021,'price'=>520000,'currency'=>'TRY','mileage'=>18000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Gazimağusa','color'=>'Black','imgKey'=>'black-bmw','body'=>'sedan','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'RWD'],
            ['brand'=>'Mercedes-Benz','model'=>'C-Class','year'=>2020,'price'=>580000,'currency'=>'TRY','mileage'=>22000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Lefkosa','color'=>'White','imgKey'=>'white-merc','body'=>'sedan','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'RWD'],
            ['brand'=>'Honda','model'=>'Civic','year'=>2019,'price'=>295000,'currency'=>'TRY','mileage'=>35000,'fuel'=>'petrol','trans'=>'manual','city'=>'Iskele','color'=>'Blue','imgKey'=>'blue-civic','body'=>'sedan','engine'=>'1.5','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Nissan','model'=>'Qashqai','year'=>2021,'price'=>420000,'currency'=>'TRY','mileage'=>15000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Girne','color'=>'Grey','imgKey'=>'grey-suv','body'=>'suv','engine'=>'1.3','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Hyundai','model'=>'Tucson','year'=>2020,'price'=>380000,'currency'=>'TRY','mileage'=>32000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Guzelyurt','color'=>'Red','imgKey'=>'red-tucson','body'=>'suv','engine'=>'1.6','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Kia','model'=>'Sportage','year'=>2021,'price'=>395000,'currency'=>'TRY','mileage'=>19000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Lefkosa','color'=>'White','imgKey'=>'white-kia','body'=>'suv','engine'=>'1.6','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Ford','model'=>'Focus','year'=>2018,'price'=>245000,'currency'=>'TRY','mileage'=>58000,'fuel'=>'diesel','trans'=>'manual','city'=>'Gazimağusa','color'=>'Silver','imgKey'=>'silver-ford','body'=>'hatchback','engine'=>'1.5','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Audi','model'=>'A4','year'=>2019,'price'=>460000,'currency'=>'TRY','mileage'=>41000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Girne','color'=>'Black','imgKey'=>'black-audi','body'=>'sedan','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Toyota','model'=>'RAV4','year'=>2020,'price'=>435000,'currency'=>'TRY','mileage'=>27000,'fuel'=>'hybrid','trans'=>'automatic','city'=>'Lefkosa','color'=>'White','imgKey'=>'white-rav4','body'=>'suv','engine'=>'2.5','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Volkswagen','model'=>'Passat','year'=>2019,'price'=>360000,'currency'=>'TRY','mileage'=>45000,'fuel'=>'diesel','trans'=>'automatic','city'=>'Iskele','color'=>'Grey','imgKey'=>'grey-passat','body'=>'sedan','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Mercedes-Benz','model'=>'E-Class','year'=>2018,'price'=>650000,'currency'=>'TRY','mileage'=>55000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Girne','color'=>'Black','imgKey'=>'black-eclass','body'=>'sedan','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'RWD'],
            ['brand'=>'Honda','model'=>'CR-V','year'=>2020,'price'=>410000,'currency'=>'TRY','mileage'=>30000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Gazimağusa','color'=>'White','imgKey'=>'white-crv','body'=>'suv','engine'=>'1.5','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'BMW','model'=>'X3','year'=>2019,'price'=>590000,'currency'=>'TRY','mileage'=>38000,'fuel'=>'diesel','trans'=>'automatic','city'=>'Lefkosa','color'=>'Blue','imgKey'=>'blue-x3','body'=>'suv','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Renault','model'=>'Megane','year'=>2019,'price'=>230000,'currency'=>'TRY','mileage'=>52000,'fuel'=>'petrol','trans'=>'manual','city'=>'Guzelyurt','color'=>'Red','imgKey'=>'red-megane','body'=>'hatchback','engine'=>'1.3','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Skoda','model'=>'Octavia','year'=>2021,'price'=>340000,'currency'=>'TRY','mileage'=>12000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Lefkosa','color'=>'Silver','imgKey'=>'silver-oct','body'=>'sedan','engine'=>'1.5','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Peugeot','model'=>'3008','year'=>2020,'price'=>310000,'currency'=>'TRY','mileage'=>24000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Girne','color'=>'White','imgKey'=>'white-3008','body'=>'suv','engine'=>'1.2','doors'=>4,'seats'=>5,'drive'=>'FWD'],
            ['brand'=>'Volvo','model'=>'XC60','year'=>2019,'price'=>530000,'currency'=>'TRY','mileage'=>39000,'fuel'=>'hybrid','trans'=>'automatic','city'=>'Iskele','color'=>'Black','imgKey'=>'black-xc60','body'=>'suv','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'AWD'],
            ['brand'=>'Mazda','model'=>'CX-5','year'=>2020,'price'=>375000,'currency'=>'TRY','mileage'=>21000,'fuel'=>'petrol','trans'=>'automatic','city'=>'Gazimağusa','color'=>'Red','imgKey'=>'red-cx5','body'=>'suv','engine'=>'2.0','doors'=>4,'seats'=>5,'drive'=>'AWD'],
        ];

        foreach ($cars as $d) {
            $brand = VehicleBrand::where('name', $d['brand'])->first();
            if (!$brand) continue;
            $model = VehicleModel::where('brand_id', $brand->id)->where('name', 'like', '%'.$d['model'].'%')->first()
                ?? VehicleModel::where('brand_id', $brand->id)->first();
            if (!$model) continue;

            [$img1, $img2] = $this->carImg($d['imgKey']);
            [$img3] = $this->carImg(array_keys($this->carImages)[rand(0, count($this->carImages)-1)], 600);

            $vehicle = Vehicle::create([
                'brand_id' => $brand->id,
                'model_id' => $model->id,
                'year' => $d['year'],
                'fuel_type' => $d['fuel'],
                'transmission' => $d['trans'],
                'body_type' => $d['body'],
                'color' => $d['color'],
                'mileage' => $d['mileage'],
                'engine_size' => $d['engine'],
                'condition' => 'used',
                'features' => ['air_conditioning','navigation','bluetooth','parking_sensors','cruise_control'],
            ]);

            $slug = Str::slug($d['year'].' '.$d['brand'].' '.$d['model'].'-'.Str::random(5));

            Listing::create([
                'user_id' => $seller->id,
                'type' => 'vehicle',
                'listable_type' => Vehicle::class,
                'listable_id' => $vehicle->id,
                'title' => $d['year'].' '.$d['brand'].' '.$d['model'],
                'slug' => $slug,
                'description' => 'This '.$d['year'].' '.$d['brand'].' '.$d['model'].' is in excellent condition with '.$d['mileage'].' km on the clock. Single owner, full service history, recently serviced. '.$d['color'].' exterior in pristine condition. '.$d['trans'].' transmission, '.$d['fuel'].' engine. Located in '.$d['city'].', North Cyprus. Serious buyers only.',
                'price' => $d['price'],
                'currency' => $d['currency'],
                'price_negotiable' => true,
                'city' => $d['city'],
                'country' => 'CY',
                'status' => 'active',
                'is_featured' => rand(0,4)===0,
                'published_at' => now()->subDays(rand(1,30)),
                'expires_at' => now()->addDays(60),
                'ai_trust_score' => rand(72,96),
                'ai_investment_score' => rand(60,88),
                'ai_price_status' => ['fair','fair','fair','slightly_overpriced'][rand(0,3)],
                'price_battery_percent' => rand(78,100),
                'ai_confidence_score' => rand(80,95),
                'market_avg_price' => $d['price']*(0.9+lcg_value()*0.2),
                'market_min_price' => $d['price']*0.78,
                'market_max_price' => $d['price']*1.22,
                'images' => [$img1, $img2, $img3],
                'thumbnail' => $img1,
            ]);
        }
        $this->command->info('  Seeded '.count($cars).' car listings with images');
    }

    private function seedProperties(User $seller): void
    {
        $properties = [
            ['type'=>'Apartment','title'=>'2 Bedroom Apartment in Lefkosa City Centre','price'=>85000,'currency'=>'GBP','beds'=>2,'baths'=>1,'area'=>85,'city'=>'Lefkosa','furnishing'=>'furnished','imgKey'=>'apt-modern','floor'=>3,'total_floors'=>8,'year_built'=>2018],
            ['type'=>'Villa','title'=>'3 Bedroom Sea View Villa in Kyrenia','price'=>320000,'currency'=>'GBP','beds'=>3,'baths'=>2,'area'=>210,'city'=>'Girne','furnishing'=>'furnished','imgKey'=>'villa-pool','floor'=>1,'total_floors'=>2,'year_built'=>2020],
            ['type'=>'Apartment','title'=>'Modern Studio Apartment Famagusta','price'=>48000,'currency'=>'GBP','beds'=>1,'baths'=>1,'area'=>45,'city'=>'Gazimağusa','furnishing'=>'furnished','imgKey'=>'apt-studio','floor'=>2,'total_floors'=>6,'year_built'=>2019],
            ['type'=>'Villa','title'=>'4 Bedroom Detached Villa Guzelyurt','price'=>280000,'currency'=>'GBP','beds'=>4,'baths'=>3,'area'=>260,'city'=>'Guzelyurt','furnishing'=>'semi-furnished','imgKey'=>'villa-luxury','floor'=>1,'total_floors'=>2,'year_built'=>2017],
            ['type'=>'Apartment','title'=>'2 Bedroom Apartment with Pool Iskele','price'=>92000,'currency'=>'GBP','beds'=>2,'baths'=>2,'area'=>95,'city'=>'Iskele','furnishing'=>'furnished','imgKey'=>'apt-seaview','floor'=>4,'total_floors'=>10,'year_built'=>2021],
            ['type'=>'Villa','title'=>'5 Bedroom Luxury Villa Long Beach Iskele','price'=>485000,'currency'=>'GBP','beds'=>5,'baths'=>4,'area'=>380,'city'=>'Iskele','furnishing'=>'furnished','imgKey'=>'villa-big','floor'=>1,'total_floors'=>3,'year_built'=>2022],
            ['type'=>'Apartment','title'=>'Penthouse Apartment Sea View Kyrenia','price'=>195000,'currency'=>'GBP','beds'=>3,'baths'=>2,'area'=>140,'city'=>'Girne','furnishing'=>'furnished','imgKey'=>'penthouse','floor'=>12,'total_floors'=>12,'year_built'=>2020],
            ['type'=>'Apartment','title'=>'1 Bedroom Apartment Near University Lefkosa','price'=>55000,'currency'=>'GBP','beds'=>1,'baths'=>1,'area'=>58,'city'=>'Lefkosa','furnishing'=>'furnished','imgKey'=>'apt-univ','floor'=>1,'total_floors'=>5,'year_built'=>2016],
            ['type'=>'Villa','title'=>'3 Bedroom Bungalow Near Beach Kyrenia','price'=>195000,'currency'=>'GBP','beds'=>3,'baths'=>2,'area'=>155,'city'=>'Girne','furnishing'=>'semi-furnished','imgKey'=>'bungalow','floor'=>1,'total_floors'=>1,'year_built'=>2015],
            ['type'=>'Apartment','title'=>'2 Bedroom Ground Floor Flat Famagusta','price'=>72000,'currency'=>'GBP','beds'=>2,'baths'=>1,'area'=>80,'city'=>'Gazimağusa','furnishing'=>'unfurnished','imgKey'=>'flat-ground','floor'=>0,'total_floors'=>4,'year_built'=>2014],
            ['type'=>'Villa','title'=>'6 Bedroom Luxury Villa with Private Pool Kyrenia','price'=>750000,'currency'=>'GBP','beds'=>6,'baths'=>5,'area'=>480,'city'=>'Girne','furnishing'=>'furnished','imgKey'=>'villa-6bed','floor'=>1,'total_floors'=>3,'year_built'=>2023],
            ['type'=>'Apartment','title'=>'3 Bedroom Apartment Lefkosa Near Hospital','price'=>128000,'currency'=>'GBP','beds'=>3,'baths'=>2,'area'=>120,'city'=>'Lefkosa','furnishing'=>'semi-furnished','imgKey'=>'apt-3bed','floor'=>5,'total_floors'=>8,'year_built'=>2019],
            ['type'=>'Apartment','title'=>'Off Plan 2+1 Apartment in Famagusta','price'=>79000,'currency'=>'GBP','beds'=>2,'baths'=>1,'area'=>82,'city'=>'Gazimağusa','furnishing'=>'unfurnished','imgKey'=>'offplan','floor'=>3,'total_floors'=>7,'year_built'=>2024],
            ['type'=>'Villa','title'=>'4 Bedroom Mountain View Villa Kyrenia','price'=>395000,'currency'=>'GBP','beds'=>4,'baths'=>3,'area'=>290,'city'=>'Girne','furnishing'=>'furnished','imgKey'=>'mtn-villa','floor'=>1,'total_floors'=>2,'year_built'=>2021],
            ['type'=>'Apartment','title'=>'2 Bedroom Duplex Apartment Kyrenia Harbour','price'=>165000,'currency'=>'GBP','beds'=>2,'baths'=>2,'area'=>110,'city'=>'Girne','furnishing'=>'furnished','imgKey'=>'duplex','floor'=>7,'total_floors'=>12,'year_built'=>2022],
            ['type'=>'Apartment','title'=>'Studio Apartment Investment Property Lefkosa','price'=>38000,'currency'=>'GBP','beds'=>1,'baths'=>1,'area'=>38,'city'=>'Lefkosa','furnishing'=>'furnished','imgKey'=>'studio-inv','floor'=>2,'total_floors'=>6,'year_built'=>2017],
            ['type'=>'Villa','title'=>'3 Bedroom Townhouse New Development Iskele','price'=>215000,'currency'=>'GBP','beds'=>3,'baths'=>2,'area'=>165,'city'=>'Iskele','furnishing'=>'unfurnished','imgKey'=>'townhouse','floor'=>1,'total_floors'=>2,'year_built'=>2024],
            ['type'=>'Apartment','title'=>'2 Bedroom Apartment Private Complex Guzelyurt','price'=>88000,'currency'=>'GBP','beds'=>2,'baths'=>1,'area'=>90,'city'=>'Guzelyurt','furnishing'=>'semi-furnished','imgKey'=>'apt-complex','floor'=>2,'total_floors'=>5,'year_built'=>2018],
            ['type'=>'Villa','title'=>'5 Bedroom Detached Villa Famagusta Countryside','price'=>320000,'currency'=>'GBP','beds'=>5,'baths'=>3,'area'=>310,'city'=>'Gazimağusa','furnishing'=>'semi-furnished','imgKey'=>'villa-5bed','floor'=>1,'total_floors'=>2,'year_built'=>2019],
            ['type'=>'Apartment','title'=>'3 Bedroom Sea View Apartment Long Beach','price'=>142000,'currency'=>'GBP','beds'=>3,'baths'=>2,'area'=>125,'city'=>'Iskele','furnishing'=>'furnished','imgKey'=>'seaview-apt','floor'=>6,'total_floors'=>10,'year_built'=>2021],
        ];

        foreach ($properties as $d) {
            $propType = PropertyType::where('name','like','%'.$d['type'].'%')->first() ?? PropertyType::first();
            if (!$propType) continue;

            [$img1, $img2] = $this->propImg($d['imgKey']);
            [$img3] = $this->propImg(array_keys($this->propImages)[rand(0, count($this->propImages)-1)], 600);

            $property = Property::create([
                'property_type_id' => $propType->id,
                'title' => $d['title'],
                'bedrooms' => $d['beds'],
                'bathrooms' => $d['baths'],
                'area_sqm' => $d['area'],
                'area_sqft' => round($d['area']*10.764),
                'furnishing' => $d['furnishing'],
                'condition' => 'good',
                'has_parking' => rand(0,1),
                'has_garden' => $d['type']==='Villa',
                'has_pool' => $d['type']==='Villa' && rand(0,1),
                'has_elevator' => $d['type']==='Apartment' && rand(0,1),
                'amenities' => ['air_conditioning','internet','water_tank','24h_security'],
                'nearby_locations' => ['airport'=>'30km','beach'=>'5km','city_centre'=>'3km'],
                'floor_number' => $d['floor'],
                'build_year' => $d['year_built'],
            ]);

            $slug = Str::slug($d['title'].'-'.Str::random(5));

            Listing::create([
                'user_id' => $seller->id,
                'type' => 'property',
                'listable_type' => Property::class,
                'listable_id' => $property->id,
                'title' => $d['title'],
                'slug' => $slug,
                'description' => $d['title'].'. '.$d['beds'].' bedrooms, '.$d['baths'].' bathrooms, '.$d['area'].'m² in '.$d['city'].', North Cyprus. '.ucfirst($d['furnishing']). '. Excellent location with easy access to amenities. Great investment opportunity in one of North Cyprus\'s most sought-after areas. Title deed available.',
                'price' => $d['price'],
                'currency' => $d['currency'],
                'price_negotiable' => true,
                'city' => $d['city'],
                'country' => 'CY',
                'status' => 'active',
                'is_featured' => rand(0,3)===0,
                'published_at' => now()->subDays(rand(1,60)),
                'expires_at' => now()->addDays(90),
                'ai_trust_score' => rand(75,97),
                'ai_investment_score' => rand(65,92),
                'ai_price_status' => ['fair','fair','fair','slightly_overpriced'][rand(0,3)],
                'price_battery_percent' => rand(80,100),
                'ai_confidence_score' => rand(82,96),
                'market_avg_price' => $d['price']*(0.88+lcg_value()*0.24),
                'market_min_price' => $d['price']*0.72,
                'market_max_price' => $d['price']*1.28,
                'images' => [$img1, $img2, $img3],
                'thumbnail' => $img1,
            ]);
        }
        $this->command->info('  Seeded '.count($properties).' property listings with images');
    }
}