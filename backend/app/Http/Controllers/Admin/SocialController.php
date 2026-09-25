<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\SocialPost;
use App\Services\InstagramService;
use App\Services\SocialPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function __construct(
        private SocialPostService $service,
        private InstagramService $instagram,
    ) {}

    /** List posts (optionally filtered by status). */
    public function index(Request $request): JsonResponse
    {
        $q = SocialPost::with('listing:id,title,type,slug,thumbnail')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        return response()->json($q->paginate(24));
    }

    /** Generate a draft post for a single listing. */
    public function generate(Request $request): JsonResponse
    {
        $request->validate(['listing_id' => 'required|string|exists:listings,id']);
        $listing = Listing::with('listable')->findOrFail($request->listing_id);
        $post = $this->service->generateForListing($listing);
        return response()->json($post->load('listing:id,title,type,slug,thumbnail'), 201);
    }

    /** Auto-generate drafts for recent active listings that have no draft yet. */
    public function generateBatch(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 30);
        $existing = SocialPost::pluck('listing_id')->filter()->all();
        $listings = Listing::with('listable')
            ->where('status', 'active')
            ->whereNotIn('id', $existing)
            ->latest('published_at')
            ->limit($limit)
            ->get();

        $created = 0;
        foreach ($listings as $listing) {
            try { $this->service->generateForListing($listing); $created++; } catch (\Throwable $e) {}
        }
        return response()->json(['created' => $created, 'message' => "Generated {$created} drafts."]);
    }

    /** Update caption / hashtags before publishing. */
    public function update(Request $request, SocialPost $socialPost): JsonResponse
    {
        $data = $request->validate([
            'caption'  => 'sometimes|string|max:2200',
            'hashtags' => 'sometimes|array|max:30',
        ]);
        $socialPost->update($data);
        return response()->json($socialPost);
    }

    /** Approve and publish to Instagram. */
    public function publish(SocialPost $socialPost): JsonResponse
    {
        if (!$this->instagram->enabled()) {
            return response()->json(['message' => 'Instagram is not configured.'], 422);
        }
        if (empty($socialPost->image_url)) {
            return response()->json(['message' => 'Post has no image to publish.'], 422);
        }
        try {
            $this->service->publish($socialPost);
            return response()->json($socialPost->fresh());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'post' => $socialPost->fresh()], 422);
        }
    }

    public function destroy(SocialPost $socialPost): JsonResponse
    {
        $socialPost->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    /** Test Instagram + Cloudinary configuration. */
    public function testConnection(): JsonResponse
    {
        return response()->json([
            'instagram'  => $this->instagram->testConnection(),
            'cloudinary' => ['ok' => app(\App\Services\CloudinaryImageService::class)->enabled()],
        ]);
    }
}
