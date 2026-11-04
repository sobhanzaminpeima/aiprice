<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'property_type_id', 'title', 'bedrooms', 'bathrooms',
        'area_sqm', 'area_sqft', 'floors', 'floor_number', 'build_year',
        'furnishing', 'condition', 'has_parking', 'has_garden', 'has_pool', 'has_elevator',
        'amenities', 'nearby_locations', 'meta',
    ];

    /** Import provenance and internal notes are never serialized to the public API. */
    protected $hidden = ['meta'];

    protected function casts(): array
    {
        return [
            'has_parking' => 'boolean',
            'has_garden' => 'boolean',
            'has_pool' => 'boolean',
            'has_elevator' => 'boolean',
            'amenities' => 'array',
            'nearby_locations' => 'array',
            'meta' => 'array',
        ];
    }

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function listing()
    {
        return $this->morphOne(Listing::class, 'listable');
    }
}
