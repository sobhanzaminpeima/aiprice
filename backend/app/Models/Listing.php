<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Listing extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasSlug;

    protected $fillable = [
        'user_id', 'listing_number', 'type', 'listable_type', 'listable_id',
        'title', 'slug', 'description', 'price', 'currency', 'price_negotiable',
        'country', 'city', 'district', 'address', 'latitude', 'longitude',
        'images', 'thumbnail', 'video_url',
        'status', 'is_featured', 'is_premium', 'is_verified',
        'view_count', 'inquiry_count', 'favorite_count',
        'published_at', 'expires_at',
        'ai_price_score', 'ai_trust_score', 'ai_investment_score',
        'ai_demand_score', 'ai_deal_score',
        'ai_price_status', 'market_min_price', 'market_max_price', 'market_avg_price',
        'price_battery_percent', 'ai_confidence_score', 'ai_analysis', 'ai_comparable_ids',
        'ai_score_breakdown', 'ai_scored_at',
        'meta', 'rejection_reason', 'import_source_key',
    ];

    /**
     * Never serialized: meta holds import provenance (source site URL/ID) and
     * an optional seller contact phone, which must only be read server-side.
     */
    protected $hidden = ['meta', 'import_source_key'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_negotiable' => 'boolean',
            'is_featured' => 'boolean',
            'is_premium' => 'boolean',
            'is_verified' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'ai_analysis' => 'array',
            'ai_comparable_ids' => 'array',
            'ai_score_breakdown' => 'array',
            'ai_scored_at' => 'datetime',
            'meta' => 'array',
            'images' => 'array',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function listable()
    {
        return $this->morphTo();
    }

    public function favorites()
    {
        return $this->hasMany(ListingFavorite::class);
    }

    public function views()
    {
        return $this->hasMany(ListingView::class);
    }

    public function documents()
    {
        return $this->hasMany(ListingDocument::class);
    }

    public function priceHistory()
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function fraudReports()
    {
        return $this->hasMany(FraudReport::class);
    }

    public function getPriceBatteryColorAttribute(): string
    {
        $pct = $this->price_battery_percent ?? 100;
        if ($pct >= 90) return 'green';
        if ($pct >= 70) return 'amber';
        return 'red';
    }

    public function getPriceBatteryLabelAttribute(): string
    {
        $pct = $this->price_battery_percent ?? 100;
        if ($pct >= 90) return 'Fair Price';
        if ($pct >= 70) return 'Slightly Overpriced';
        return 'Overpriced';
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($listing) {
            // listing_number is unique; retry on the rare collision rather than
            // letting the insert fail. Older listings keep their HP- numbers.
            do {
                $number = 'AP-' . strtoupper(substr($listing->type, 0, 1)) . '-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            } while (static::withTrashed()->where('listing_number', $number)->exists());
            $listing->listing_number ??= $number;

        });
        // Auto-place on the map from the city name unless coordinates were
        // explicitly provided (e.g. a precise pin dropped by the seller).
        static::saving(function ($listing) {
            $cityChanged = $listing->exists && $listing->isDirty('city') && ! $listing->isDirty('latitude');
            if ($listing->city && ((! $listing->latitude && ! $listing->longitude) || $cityChanged)) {
                $coords = app(\App\Services\GeoService::class)->coordinatesFor($listing->city, (string) ($listing->id ?: $listing->title ?: uniqid()));
                if ($coords) {
                    [$listing->latitude, $listing->longitude] = $coords;
                }
            }
        });
    }
}
