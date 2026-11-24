<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\CompetitorSource;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view_dashboard', 'manage_users', 'manage_listings', 'manage_vehicles',
            'manage_properties', 'manage_rentals', 'manage_subscriptions', 'manage_payments',
            'manage_ai', 'manage_competitors', 'manage_cms', 'manage_settings',
            'manage_support', 'view_analytics', 'manage_advertisements',
            'approve_listings', 'approve_kyc', 'manage_fraud', 'manage_imports',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'super_admin' => $permissions,
            'admin' => $permissions,
            'moderator' => ['view_dashboard', 'manage_listings', 'approve_listings', 'manage_fraud'],
            'support' => ['view_dashboard', 'manage_support'],
            'finance_manager' => ['view_dashboard', 'manage_payments', 'manage_subscriptions', 'view_analytics'],
            'ai_analyst' => ['view_dashboard', 'manage_ai', 'manage_competitors', 'view_analytics'],
            'dealer' => [],
            'agency' => [],
            'seller' => [],
            'buyer' => [],
            'investor' => [],
            'premium' => [],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if (! empty($perms)) {
                $role->syncPermissions($perms);
            }
        }

        // Password matches DEVELOPMENT.md's documented admin login exactly —
        // keep these in sync if either changes.
        if (! app()->environment('production')) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@aiprice.app'],
                [
                    'name' => 'AIPrice Admin',
                    'username' => 'admin',
                    'password' => Hash::make('Admin@123456'),
                    'role' => 'super_admin',
                    'is_active' => true,
                    'is_verified' => true,
                    'kyc_verified' => true,
                    'force_password_change' => false,
                    'email_verified_at' => now(),
                ]
            );
            $admin->syncRoles(['super_admin']);
            UserProfile::firstOrCreate(['user_id' => $admin->id], ['business_name' => 'AIPrice.app', 'country' => 'GB']);
        }

        $brandsData = [
            ['name' => 'BMW', 'country' => 'DE', 'models' => ['3 Series', '5 Series', '7 Series', 'X3', 'X5', 'M3', 'M5']],
            ['name' => 'Mercedes-Benz', 'country' => 'DE', 'models' => ['C-Class', 'E-Class', 'S-Class', 'GLE', 'GLC', 'AMG GT']],
            ['name' => 'Toyota', 'country' => 'JP', 'models' => ['Corolla', 'Camry', 'RAV4', 'Land Cruiser', 'Prius', 'Hilux']],
            ['name' => 'Ford', 'country' => 'US', 'models' => ['Focus', 'Fiesta', 'Mustang', 'Explorer', 'F-150', 'Kuga']],
            ['name' => 'Volkswagen', 'country' => 'DE', 'models' => ['Golf', 'Passat', 'Tiguan', 'Polo', 'Arteon', 'ID.4']],
            ['name' => 'Audi', 'country' => 'DE', 'models' => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7', 'RS6']],
            ['name' => 'Honda', 'country' => 'JP', 'models' => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Jazz']],
            ['name' => 'Porsche', 'country' => 'DE', 'models' => ['911', 'Cayenne', 'Macan', 'Panamera', 'Taycan']],
            ['name' => 'Land Rover', 'country' => 'GB', 'models' => ['Range Rover', 'Discovery', 'Defender', 'Freelander']],
            ['name' => 'Ferrari', 'country' => 'IT', 'models' => ['488', 'F8', 'SF90', 'Roma', 'Portofino']],
        ];

        foreach ($brandsData as $brandData) {
            $brand = VehicleBrand::firstOrCreate(['name' => $brandData['name']], ['country' => $brandData['country'], 'is_active' => true]);
            foreach ($brandData['models'] as $modelName) {
                VehicleModel::firstOrCreate(['brand_id' => $brand->id, 'name' => $modelName], ['is_active' => true]);
            }
        }

        $propertyTypes = ['Apartment', 'Villa', 'Townhouse', 'Penthouse', 'Studio', 'Duplex', 'Land', 'Commercial', 'Office', 'Shop'];
        foreach ($propertyTypes as $type) {
            PropertyType::firstOrCreate(['name' => $type], ['is_active' => true]);
        }

        // Subscription plans are created by the 2026_11_25_000001 migration.

        $aiModels = [
            ['name' => 'GPT-4o', 'provider' => 'openai', 'model_id' => 'gpt-4o', 'purpose' => 'price_analysis', 'is_default' => true, 'cost_per_1k_tokens' => 0.005],
            ['name' => 'GPT-4o Mini', 'provider' => 'openai', 'model_id' => 'gpt-4o-mini', 'purpose' => 'trust_score', 'is_default' => true, 'cost_per_1k_tokens' => 0.00015],
            ['name' => 'Claude Sonnet', 'provider' => 'anthropic', 'model_id' => 'claude-sonnet-4-6', 'purpose' => 'investment', 'cost_per_1k_tokens' => 0.003],
            ['name' => 'Gemini Pro', 'provider' => 'gemini', 'model_id' => 'gemini-pro', 'purpose' => 'fraud', 'cost_per_1k_tokens' => 0.001],
        ];

        foreach ($aiModels as $model) {
            AiModel::firstOrCreate(['model_id' => $model['model_id']], $model + ['is_active' => true]);
        }

        $sources = [
            ['name' => 'KKT Carabam', 'url' => 'https://www.kktcarabam.com/', 'type' => 'vehicle'],
            ['name' => 'Hangiev', 'url' => 'https://www.hangiev.com/', 'type' => 'property'],
            ['name' => 'Hangiev Resales', 'url' => 'https://www.hangiev.com/project-resales', 'type' => 'property'],
            ['name' => 'Noyanlar Invest', 'url' => 'https://noyanlarinvest.com/', 'type' => 'property'],
        ];

        foreach ($sources as $source) {
            CompetitorSource::firstOrCreate(['url' => $source['url']], $source + ['is_active' => true, 'scrape_interval' => 'daily']);
        }

        $settings = [
            ['group' => 'general', 'key' => 'site_name', 'value' => 'AIPrice.app', 'type' => 'text', 'label' => 'Site Name', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_tagline', 'value' => 'AI-Powered Truth In Every Transaction', 'type' => 'text', 'label' => 'Tagline', 'is_public' => true],
            ['group' => 'general', 'key' => 'default_currency', 'value' => 'GBP', 'type' => 'text', 'label' => 'Default Currency', 'is_public' => true],
            ['group' => 'ai', 'key' => 'ai_price_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'AI Price Intelligence'],
            ['group' => 'ai', 'key' => 'ai_trust_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'AI Trust Score'],
            ['group' => 'ai', 'key' => 'ai_fraud_enabled', 'value' => '1', 'type' => 'boolean', 'label' => 'AI Fraud Detection'],
            ['group' => 'listings', 'key' => 'listing_requires_approval', 'value' => '1', 'type' => 'boolean', 'label' => 'Listings Require Approval'],
            ['group' => 'listings', 'key' => 'listing_expiry_days', 'value' => '90', 'type' => 'number', 'label' => 'Listing Expiry Days'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
