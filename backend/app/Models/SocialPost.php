<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    use HasUuids;

    protected $fillable = [
        'listing_id', 'platform', 'status', 'source_image', 'image_url',
        'caption', 'hashtags', 'ig_media_id', 'ig_permalink', 'error', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'hashtags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
