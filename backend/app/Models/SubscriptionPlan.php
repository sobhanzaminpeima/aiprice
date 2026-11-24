<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class SubscriptionPlan extends Model
{
    use HasUuids, SoftDeletes, HasSlug;

    protected $fillable = [
        'name', 'slug', 'description', 'type', 'price_monthly', 'price_yearly',
        'currency', 'max_listings', 'max_photos_per_listing',
        'ai_price_analysis', 'ai_trust_score', 'ai_investment_score',
        'featured_listing', 'priority_support', 'features', 'is_active', 'sort_order',
        'max_featured', 'listing_duration_days', 'trial_days', 'is_highlighted',
    ];

    /** Plan given to anyone without a paid subscription. */
    public const FREE_SLUG = 'private-free';

    protected function casts(): array
    {
        return [
            'ai_price_analysis' => 'boolean',
            'ai_trust_score' => 'boolean',
            'ai_investment_score' => 'boolean',
            'featured_listing' => 'boolean',
            'priority_support' => 'boolean',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'max_listings' => 'integer',
            'max_photos_per_listing' => 'integer',
            'max_featured' => 'integer',
            'listing_duration_days' => 'integer',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
            'features' => 'array',
        ];
    }

    public function isFree(): bool
    {
        return (float) $this->price_monthly <= 0 && (float) $this->price_yearly <= 0;
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug')->doNotGenerateSlugsOnUpdate();
    }
}
