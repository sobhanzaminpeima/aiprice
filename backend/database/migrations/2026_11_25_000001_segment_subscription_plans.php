<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Launch pricing: separate plan ladders for private sellers, car dealers and
 * real-estate agencies, a one-month free trial on paid plans, and the plan
 * limits the marketplace actually enforces (listing slots, photos, featured
 * slots, listing lifetime).
 *
 * The earlier generic plans are switched off, not deleted, so anyone already
 * subscribed to one keeps it until it ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('max_featured')->default(0)->after('max_photos_per_listing');
            $table->integer('listing_duration_days')->default(60)->after('max_featured');
            $table->integer('trial_days')->default(0)->after('listing_duration_days');
            $table->boolean('is_highlighted')->default(false)->after('trial_days');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('gateway_subscription_id');
            $table->timestamp('reminder_sent_at')->nullable()->after('notes');
        });

        Schema::table('users', function (Blueprint $table) {
            // A free trial is offered once per account, whichever plan it was on.
            $table->timestamp('trial_used_at')->nullable();
        });

        DB::table('subscription_plans')
            ->whereIn('slug', ['free', 'starter', 'professional', 'enterprise'])
            ->update(['is_active' => false, 'updated_at' => now()]);

        foreach (self::plans() as $i => $plan) {
            $row = $plan + [
                'currency' => 'GBP',
                'price_yearly' => $plan['price_monthly'] * 10, // two months free
                'is_active' => true,
                'sort_order' => $i + 1,
                'features' => null,
                'updated_at' => now(),
            ];
            $existing = DB::table('subscription_plans')->where('slug', $plan['slug'])->first();
            if ($existing) {
                DB::table('subscription_plans')->where('id', $existing->id)->update($row);
            } else {
                DB::table('subscription_plans')->insert($row + ['id' => (string) Str::uuid(), 'created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('subscription_plans')->whereIn('slug', array_column(self::plans(), 'slug'))->delete();
        DB::table('subscription_plans')
            ->whereIn('slug', ['free', 'starter', 'professional', 'enterprise'])
            ->update(['is_active' => true]);

        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('trial_used_at'));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn(['notes', 'reminder_sent_at']));
        Schema::table('subscription_plans', fn (Blueprint $t) => $t->dropColumn(['max_featured', 'listing_duration_days', 'trial_days', 'is_highlighted']));
    }

    /** @return array<int, array<string, mixed>> */
    public static function plans(): array
    {
        $base = ['ai_price_analysis' => true, 'ai_trust_score' => false, 'ai_investment_score' => false, 'featured_listing' => false, 'priority_support' => false, 'is_highlighted' => false];

        return [
            // Private sellers: free, so the marketplace fills up with stock.
            ['slug' => 'private-free', 'name' => 'Private', 'type' => 'seller', 'price_monthly' => 0, 'trial_days' => 0,
                'description' => 'For individuals selling their own car or home.',
                'max_listings' => 2, 'max_photos_per_listing' => 10, 'max_featured' => 0, 'listing_duration_days' => 60] + $base,

            // Car dealers (galeri)
            ['slug' => 'dealer-start', 'name' => 'Dealer Start', 'type' => 'dealer', 'price_monthly' => 19, 'trial_days' => 30,
                'description' => 'For small car dealers getting started online.',
                'max_listings' => 15, 'max_photos_per_listing' => 15, 'max_featured' => 0, 'listing_duration_days' => 90] + $base,
            ['slug' => 'dealer-pro', 'name' => 'Dealer Pro', 'type' => 'dealer', 'price_monthly' => 49, 'trial_days' => 30,
                'description' => 'The full toolkit for a typical showroom.',
                'max_listings' => 60, 'max_photos_per_listing' => 25, 'max_featured' => 5, 'listing_duration_days' => 90,
                'featured_listing' => true, 'ai_trust_score' => true, 'is_highlighted' => true] + $base,
            ['slug' => 'dealer-max', 'name' => 'Dealer Max', 'type' => 'dealer', 'price_monthly' => 99, 'trial_days' => 30,
                'description' => 'For large dealers and multi-branch showrooms.',
                'max_listings' => 200, 'max_photos_per_listing' => 30, 'max_featured' => 15, 'listing_duration_days' => 120,
                'featured_listing' => true, 'ai_trust_score' => true, 'ai_investment_score' => true, 'priority_support' => true] + $base,

            // Real-estate agencies (emlak)
            ['slug' => 'agency-start', 'name' => 'Agency Start', 'type' => 'agency', 'price_monthly' => 29, 'trial_days' => 30,
                'description' => 'For independent agents and small offices.',
                'max_listings' => 25, 'max_photos_per_listing' => 20, 'max_featured' => 0, 'listing_duration_days' => 90] + $base,
            ['slug' => 'agency-pro', 'name' => 'Agency Pro', 'type' => 'agency', 'price_monthly' => 69, 'trial_days' => 30,
                'description' => 'The full toolkit for a typical estate agency.',
                'max_listings' => 100, 'max_photos_per_listing' => 30, 'max_featured' => 5, 'listing_duration_days' => 120,
                'featured_listing' => true, 'ai_trust_score' => true, 'ai_investment_score' => true, 'is_highlighted' => true] + $base,
            ['slug' => 'agency-elite', 'name' => 'Agency Elite', 'type' => 'agency', 'price_monthly' => 149, 'trial_days' => 30,
                'description' => 'For developers and large agencies with big portfolios.',
                'max_listings' => 300, 'max_photos_per_listing' => 30, 'max_featured' => 15, 'listing_duration_days' => 180,
                'featured_listing' => true, 'ai_trust_score' => true, 'ai_investment_score' => true, 'priority_support' => true] + $base,
        ];
    }
};
