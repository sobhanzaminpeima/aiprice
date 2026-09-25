<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    // ---- Blog posts ----------------------------------------------------

    public function posts(Request $request): JsonResponse
    {
        return response()->json(
            BlogPost::with(['category:id,name', 'author:id,name'])
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
                ->orderByDesc('updated_at')
                ->paginate(20)
        );
    }

    public function showPost(string $id): JsonResponse
    {
        return response()->json(BlogPost::with('category:id,name')->findOrFail($id));
    }

    public function storePost(Request $request): JsonResponse
    {
        $data = $this->validatePost($request);
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['published_at'] = $this->publishedAt($data);

        return response()->json(BlogPost::create($data), 201);
    }

    public function updatePost(Request $request, string $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        $data = $this->validatePost($request, $post);

        if (isset($data['slug']) && $data['slug'] !== $post->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $post->id);
        }
        // Keep the original publish date when re-saving an already-published post.
        $data['published_at'] = $this->publishedAt($data, $post);

        $post->update($data);
        return response()->json($post->fresh('category:id,name'));
    }

    public function destroyPost(string $id): JsonResponse
    {
        BlogPost::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ---- Categories ----------------------------------------------------

    public function categories(): JsonResponse
    {
        return response()->json(BlogCategory::withCount('posts')->orderBy('name')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $v = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:500']);
        $v['slug'] = Str::slug($v['name']);
        abort_if(BlogCategory::where('slug', $v['slug'])->exists(), 422, 'A category with that name exists.');

        return response()->json(BlogCategory::create($v + ['is_active' => true]), 201);
    }

    public function destroyCategory(string $id): JsonResponse
    {
        $category = BlogCategory::withCount('posts')->findOrFail($id);
        abort_if($category->posts_count > 0, 422, 'Move or delete this category’s posts first.');
        $category->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ---- FAQs ----------------------------------------------------------

    public function faqs(): JsonResponse
    {
        return response()->json(Faq::orderBy('category')->orderBy('sort_order')->get());
    }

    public function storeFaq(Request $request): JsonResponse
    {
        return response()->json(Faq::create($this->validateFaq($request)), 201);
    }

    public function updateFaq(Request $request, string $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->update($this->validateFaq($request, partial: true));
        return response()->json($faq);
    }

    public function destroyFaq(string $id): JsonResponse
    {
        Faq::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ---- helpers -------------------------------------------------------

    private function validatePost(Request $request, ?BlogPost $post = null): array
    {
        $req = $post ? 'sometimes' : 'required';

        return $request->validate([
            'title' => "{$req}|string|max:200",
            'slug' => 'nullable|string|max:200|alpha_dash',
            'excerpt' => 'nullable|string|max:500',
            'content' => "{$req}|string",
            'category_id' => 'nullable|exists:blog_categories,id',
            'featured_image' => 'nullable|url|max:500',
            'status' => ['sometimes', Rule::in(['draft', 'published', 'scheduled'])],
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:40',
            'seo_title' => 'nullable|string|max:70',
            'seo_description' => 'nullable|string|max:160',
        ]);
    }

    private function validateFaq(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'question' => "{$req}|string|max:300",
            'answer' => "{$req}|string|max:5000",
            'category' => 'nullable|string|max:60',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);
    }

    private function publishedAt(array $data, ?BlogPost $post = null)
    {
        $status = $data['status'] ?? $post?->status ?? 'draft';

        return match ($status) {
            'published' => $post?->published_at ?? now(),
            'scheduled' => $data['published_at'] ?? $post?->published_at ?? abort(422, 'A scheduled post needs a publish date.'),
            default => null,
        };
    }

    private function uniqueSlug(string $source, ?string $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'post';
        $slug = $base;
        $i = 2;
        while (BlogPost::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
