<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudReport;
use App\Models\Listing;
use App\Services\FraudSignals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    public function __construct(private FraudSignals $signals)
    {
    }

    public function overview(): JsonResponse
    {
        $flagged = $this->flaggedListings();

        return response()->json([
            'stats' => [
                'pending' => FraudReport::where('status', 'pending')->count(),
                'investigating' => FraudReport::where('status', 'investigating')->count(),
                'confirmed_30d' => FraudReport::where('status', 'confirmed')->where('updated_at', '>=', now()->subDays(30))->count(),
                'dismissed_30d' => FraudReport::where('status', 'dismissed')->where('updated_at', '>=', now()->subDays(30))->count(),
                'high_risk_listings' => $flagged->where('risk.score', '>=', 50)->count(),
            ],
            'flagged_listings' => $flagged->take(20)->values(),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $status = $request->input('status', 'open');

        $q = FraudReport::with(['listing:id,slug,title,price,currency,status,user_id', 'listing.user:id,name,email,kyc_verified', 'reporter:id,name,email'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'investigating']))
            ->when(!in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('risk_score')
            ->orderByDesc('created_at');

        return response()->json($q->paginate(20));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'status' => 'required|in:pending,investigating,confirmed,dismissed',
            'note' => 'nullable|string|max:1000',
            'archive_listing' => 'boolean',
        ]);

        $report = FraudReport::with('listing')->findOrFail($id);
        $analysis = $report->ai_analysis ?? [];
        if (!empty($v['note'])) {
            $analysis['review_notes'][] = ['by' => $request->user()->id, 'at' => now()->toIso8601String(), 'note' => $v['note']];
        }
        $report->update(['status' => $v['status'], 'ai_analysis' => $analysis]);

        // Confirming fraud takes the listing down and closes sibling reports.
        if ($v['status'] === 'confirmed' && ($v['archive_listing'] ?? true) && $report->listing) {
            $report->listing->update(['status' => 'archived']);
            FraudReport::where('listing_id', $report->listing_id)
                ->where('id', '!=', $report->id)
                ->whereIn('status', ['pending', 'investigating'])
                ->update(['status' => 'confirmed']);
        }

        return response()->json($report->fresh(['listing', 'reporter:id,name,email']));
    }

    /** Active listings with at least one risk signal, riskiest first. */
    private function flaggedListings()
    {
        return Listing::with('user:id,name,email,kyc_verified,created_at,email_verified_at')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['id', 'slug', 'title', 'price', 'currency', 'city', 'user_id', 'images', 'thumbnail',
                'ai_price_status', 'ai_price_score', 'ai_trust_score', 'created_at'])
            ->map(function (Listing $l) {
                $l->setAttribute('risk', $this->signals->assess($l));
                return $l;
            })
            ->filter(fn ($l) => $l->risk['score'] > 0)
            ->sortByDesc(fn ($l) => $l->risk['score'])
            ->values();
    }
}
