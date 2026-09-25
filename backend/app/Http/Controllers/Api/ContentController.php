<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public, read-only blog and FAQ. Drafts and future-scheduled posts never leak. */
class ContentController extends Controller
{
    public function posts(Request $request): JsonResponse
    {
        return response()->json(
            BlogPost::published()
                ->with(['category:id,name,slug', 'author:id,name'])
                ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
                ->when($request->tag, fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
                ->orderByDesc('published_at')
                ->paginate(12, ['id', 'category_id', 'user_id', 'title', 'slug', 'excerpt', 'featured_image', 'tags', 'published_at', 'view_count'])
        );
    }

    public function post(string $slug): JsonResponse
    {
        $post = BlogPost::published()->with(['category:id,name,slug', 'author:id,name'])->where('slug', $slug)->firstOrFail();
        $post->increment('view_count');

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'featured_image', 'published_at']);

        return response()->json(['post' => $post, 'related' => $related]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(
            BlogCategory::where('is_active', true)
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
        );
    }

    public function faqs(): JsonResponse
    {
        return response()->json(
            Faq::where('is_active', true)->orderBy('category')->orderBy('sort_order')->get(['id', 'category', 'question', 'answer'])
        );
    }
}
