<?php

namespace App\Models\Imports;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceListingImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_listing_id', 'position', 'source_url', 'sha256', 'original_path', 'webp_path', 'thumb_path',
        'public_paths', 'width', 'height', 'bytes', 'is_cover', 'status', 'error',
    ];

    protected function casts(): array
    {
        return ['public_paths' => 'array', 'is_cover' => 'boolean'];
    }

    public function sourceListing(): BelongsTo
    {
        return $this->belongsTo(SourceListing::class);
    }
}
