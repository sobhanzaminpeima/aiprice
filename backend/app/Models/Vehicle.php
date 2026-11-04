<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'brand_id', 'model_id', 'variant', 'year', 'fuel_type', 'transmission',
        'body_type', 'color', 'mileage', 'engine_size', 'horsepower',
        'vin', 'registration', 'condition', 'features', 'meta',
    ];

    /** Import provenance and internal notes are never serialized to the public API. */
    protected $hidden = ['meta'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'meta' => 'array',
        ];
    }

    public function brand()
    {
        return $this->belongsTo(VehicleBrand::class);
    }

    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function listing()
    {
        return $this->morphOne(Listing::class, 'listable');
    }
}
