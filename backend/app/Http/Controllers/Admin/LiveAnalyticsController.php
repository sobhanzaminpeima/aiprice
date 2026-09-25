<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\ListingView;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Near-real-time activity from listing views and recent events. The admin page
 * polls this every 15 seconds; everything here is a cheap indexed count.
 */
class LiveAnalyticsController extends Controller
{
    public function snapshot(): JsonResponse
    {
        $now = now();
        $views = fn ($minutes) => ListingView::where('created_at', '>=', $now->copy()->subMinutes($minutes));

        // Views per minute for the last 30 minutes, zero-filled.
        $perMinute = $views(30)->get(['created_at'])
            ->countBy(fn ($v) => $v->created_at->format('H:i'));
        $series = collect(range(29, 0))->map(function ($i) use ($now, $perMinute) {
            $label = $now->copy()->subMinutes($i)->format('H:i');
            return ['minute' => $label, 'views' => $perMinute[$label] ?? 0];
        });

        $topListings = $views(60)
            ->select('listing_id', DB::raw('COUNT(*) as views'))
            ->groupBy('listing_id')
            ->orderByDesc('views')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $l = Listing::find($row->listing_id, ['id', 'slug', 'title', 'type', 'city', 'price', 'currency']);
                return $l ? ['listing' => $l, 'views' => (int) $row->views] : null;
            })
            ->filter()
            ->values();

        return response()->json([
            'at' => $now->toIso8601String(),
            'metrics' => [
                // Distinct IPs is an approximation of people, not a login count.
                'active_visitors_5m' => $views(5)->distinct('ip_address')->count('ip_address'),
                'views_30m' => $views(30)->count(),
                'views_today' => ListingView::where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'signups_today' => User::where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'listings_today' => Listing::where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'inquiries_today' => Inquiry::where('created_at', '>=', $now->copy()->startOfDay())->count(),
            ],
            'views_per_minute' => $series,
            'top_listings_1h' => $topListings,
            'feed' => $this->feed(),
        ]);
    }

    /** The 15 most recent platform events, newest first. */
    private function feed()
    {
        $signups = User::latest()->limit(5)->get(['id', 'name', 'role', 'created_at'])
            ->map(fn ($u) => ['type' => 'signup', 'at' => $u->created_at, 'text' => "{$u->name} joined as {$u->role}"]);

        $listings = Listing::latest()->limit(5)->get(['id', 'title', 'type', 'status', 'created_at'])
            ->map(fn ($l) => ['type' => 'listing', 'at' => $l->created_at, 'text' => "New {$l->type}: {$l->title}", 'status' => $l->status]);

        $inquiries = Inquiry::with('listing:id,title')->latest()->limit(5)->get()
            ->map(fn ($i) => ['type' => 'inquiry', 'at' => $i->created_at, 'text' => 'Inquiry on '.($i->listing?->title ?? 'a listing')]);

        return $signups->concat($listings)->concat($inquiries)->sortByDesc('at')->take(15)->values();
    }
}
