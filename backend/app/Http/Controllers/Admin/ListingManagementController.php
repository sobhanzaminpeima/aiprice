<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeListingPrice;
use App\Models\Listing;
use App\Notifications\ListingReviewed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Listing::with(['user:id,name,email', 'listable'])->orderByDesc('created_at');
        if ($request->status) $q->where('status', $request->status);
        if ($request->type) $q->where('type', $request->type);
        if ($request->search) $q->where('title', 'like', '%' . $request->search . '%');
        return response()->json($q->paginate(20));
    }

    public function approve(string $id): JsonResponse
    {
        $listing = Listing::with('user')->findOrFail($id);
        $listing->update([
            'status' => 'active', 'published_at' => now(), 'expires_at' => now()->addDays(90), 'rejection_reason' => null,
        ]);

        // Scoring calls the LLM for its insight sentence — run it on the queue.
        AnalyzeListingPrice::dispatch($listing->id);
        $listing->user?->notify(new ListingReviewed($listing, approved: true));

        return response()->json(['message' => 'Listing approved and AI analysis queued.', 'listing' => $listing]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $listing = Listing::with('user')->findOrFail($id);
        $listing->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);

        $listing->user?->notify(new ListingReviewed($listing, approved: false, reason: $request->reason));

        return response()->json(['message' => 'Listing rejected.']);
    }

    public function feature(string $id): JsonResponse
    {
        $listing = Listing::findOrFail($id);
        $listing->update(['is_featured' => !$listing->is_featured]);
        return response()->json(['is_featured' => $listing->is_featured]);
    }

    public function destroy(string $id): JsonResponse
    {
        Listing::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function reanalyze(string $id): JsonResponse
    {
        $listing = Listing::findOrFail($id);
        AnalyzeListingPrice::dispatch($listing->id);
        return response()->json(['message' => 'AI re-analysis queued.', 'listing' => $listing], 202);
    }
}
