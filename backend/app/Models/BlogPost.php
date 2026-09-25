<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'excerpt', 'content', 'featured_image',
        'status', 'tags', 'seo_title', 'seo_description', 'published_at', 'view_count',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $q): Builder
    {
        // A scheduled post goes live on its own once its publish date passes.
        return $q->whereIn('status', ['published', 'scheduled'])->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }
}
