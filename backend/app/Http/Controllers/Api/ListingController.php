<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingFavorite;
use App\Models\ListingView;
use App\Models\PriceHistory;
use App\Models\Property;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\AI\RankingEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    public function __construct(private RankingEngineService $rankingEngine)
    {
    }

    public function featured(): JsonResponse
    {
        $listings = Listing::with(['listable'])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('is_featured', true)->orWhereNotNull('ai_trust_score');
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('ai_trust_score')
            ->limit(12)
            ->get();
        return response()->json($listings);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'brand' => 'sometimes|nullable|string|max:60', 'model' => 'sometimes|nullable|string|max:60',
            'body' => 'sometimes|nullable|string|max:40', 'fuel_type' => 'sometimes|nullable|string|max:20',
            'transmission' => 'sometimes|nullable|string|max:20',
            'min_year' => 'sometimes|nullable|integer|min:1950|max:2100', 'max_year' => 'sometimes|nullable|integer|min:1950|max:2100',
            'max_mileage' => 'sometimes|nullable|integer|min:0',
            'property_type' => 'sometimes|nullable|string|max:60',
            'min_price' => 'sometimes|nullable|numeric|min:0', 'max_price' => 'sometimes|nullable|numeric|min:0',
            'search' => 'sometimes|nullable|string|max:100', 'city' => 'sometimes|nullable|string|max:100',
        ]);
        $query = Listing::with(['listable'])->where('status', 'active');
        // Vehicle filters (case-insensitive so "Petrol" matches "petrol").
        $vehicleFilters = array_filter($request->only(['brand', 'model', 'body', 'fuel_type', 'transmission', 'min_year', 'max_year', 'max_mileage']), fn ($v) => $v !== null && $v !== '');
        if ($vehicleFilters) {
            $query->whereHasMorph('listable', [\App\Models\Vehicle::class], function ($q) use ($vehicleFilters) {
                if (isset($vehicleFilters['brand'])) {
                    $q->whereHas('brand', fn ($b) => $b->whereRaw('LOWER(name) = ?', [mb_strtolower($vehicleFilters['brand'])]));
                }
                if (isset($vehicleFilters['model'])) {
                    $q->whereHas('model', fn ($m) => $m->whereRaw('LOWER(name) = ?', [mb_strtolower($vehicleFilters['model'])]));
                }
                foreach (['body' => 'body_type', 'fuel_type' => 'fuel_type', 'transmission' => 'transmission'] as $param => $col) {
                    if (isset($vehicleFilters[$param])) {
                        $q->whereRaw("LOWER({$col}) = ?", [mb_strtolower($vehicleFilters[$param])]);
                    }
                }
                if (isset($vehicleFilters['min_year'])) $q->where('year', '>=', (int) $vehicleFilters['min_year']);
                if (isset($vehicleFilters['max_year'])) $q->where('year', '<=', (int) $vehicleFilters['max_year']);
                if (isset($vehicleFilters['max_mileage'])) $q->where('mileage', '<=', (int) $vehicleFilters['max_mileage']);
            });
        }
        if ($request->filled('property_type')) {
            $query->whereHasMorph('listable', [\App\Models\Property::class], function ($q) use ($request) {
                $q->whereHas('propertyType', fn ($t) => $t->whereRaw('LOWER(name) = ?', [mb_strtolower($request->property_type)]));
            });
        }
        if ($request->type) $query->where('type', $request->type);
        if ($request->city) $query->where('city', 'like', '%' . $request->city . '%');
        if ($request->min_price) $query->where('price', '>=', $request->min_price);
        if ($request->max_price) $query->where('price', '<=', $request->max_price);
        if ($request->min_bedrooms) {
            $query->whereHasMorph('listable', [\App\Models\Property::class, \App\Models\Rental::class], function ($q) use ($request) {
                $q->where('bedrooms', '>=', $request->min_bedrooms);
            });
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('city', 'like', '%' . $request->search . '%');
            });
        }
        // FIND / DEAL / INVEST — dynamic ranking (see RankingEngineService).
        // Takes priority over `sort` when present; `sort` still works for
        // buyers who just want plain price/newest ordering.
        if ($request->mode && in_array($request->mode, RankingEngineService::MODES, true)) {
            $this->rankingEngine->applyToQuery($query, $request->mode);
            $result = $query->paginate($request->per_page ?? 12);

            return response()->json([
                ...$result->toArray(),
                'ranking_mode' => $request->mode,
                'ranking_weights' => $this->rankingEngine->weights($request->mode),
            ]);
        }

        $sort = $request->sort ?? 'newest';
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'most_viewed' => $query->orderByDesc('view_count'),
            'trust_score' => $query->orderByDesc('ai_trust_score'),
            default => $query->orderByDesc('published_at'),
        };
        return response()->json($query->paginate($request->per_page ?? 12));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        // The public payload carries a privacy-safe seller summary only (first
        // name + masked phone). The full number needs sign-in: see sellerPhone().
        $listing = Listing::with(['listable'])
            ->where('slug', $slug)->where('status', 'active')->firstOrFail();
        // This route is public, so resolve the optional bearer token explicitly.
        $user = $request->user('sanctum');
        $sellerContact = app(\App\Services\SellerContactService::class)->publicSummary($listing);
        $listing->unsetRelation('user');
        // One view per visitor per listing per 30 minutes, so refreshes and the
        // SPA's double fetch don't inflate counts sellers and ranking rely on.
        $viewer = $user?->id;
        $alreadyCounted = ListingView::where('listing_id', $listing->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->where(fn ($q) => $viewer ? $q->where('user_id', $viewer) : $q->whereNull('user_id')->where('ip_address', $request->ip()))
            ->exists();
        if (!$alreadyCounted) {
            $listing->increment('view_count');
            ListingView::create(['listing_id' => $listing->id, 'user_id' => $viewer, 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255)]);
        }
        // Price-band filter: ±40% of current listing price so similar class is shown
        $price = $listing->price;
        $comparableQuery = Listing::where('type', $listing->type)
            ->where('id', '!=', $listing->id)
            ->where('status', 'active');
        if ($price && $price > 0) {
            $comparableQuery->whereBetween('price', [$price * 0.6, $price * 1.4]);
        }
        $comparables = $comparableQuery->inRandomOrder()->limit(6)
            ->get(['id', 'title', 'price', 'currency', 'slug', 'ai_price_status', 'price_battery_percent', 'ai_trust_score', 'thumbnail', 'images', 'city']);
        // Fallback: if not enough results in band, widen to ±65%
        if ($comparables->count() < 3 && $price && $price > 0) {
            $comparables = Listing::where('type', $listing->type)
                ->where('id', '!=', $listing->id)
                ->where('status', 'active')
                ->whereBetween('price', [$price * 0.35, $price * 1.65])
                ->inRandomOrder()->limit(6)
                ->get(['id', 'title', 'price', 'currency', 'slug', 'ai_price_status', 'price_battery_percent', 'ai_trust_score', 'thumbnail', 'images', 'city']);
        }
        return response()->json([
            'listing' => $listing,
            'seller_contact' => $sellerContact,
            'comparable_listings' => $comparables,
            'is_favorited' => $user
                ? ListingFavorite::where('user_id', $user->id)->where('listing_id', $listing->id)->exists()
                : false,
        ]);
    }

    /**
     * Full seller phone number: signed-in users only (route is behind
     * auth:sanctum + throttle). Every reveal is recorded as a lead.
     */
    public function sellerPhone(Request $request, string $id): JsonResponse
    {
        $listing = Listing::where('status', 'active')->findOrFail($id);
        $phone = app(\App\Services\SellerContactService::class)->phoneFor($listing);
        if ($phone === null) {
            return response()->json(['message' => 'This seller has not shared a phone number. Use "Send to seller" instead.'], 404);
        }
        if ($listing->user_id !== $request->user()->id) {
            \Illuminate\Support\Facades\DB::table('listing_contact_reveals')->insert([
                'listing_id' => $listing->id, 'user_id' => $request->user()->id,
                'ip_address' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json(['phone' => $phone, 'whatsapp' => preg_replace('/\D+/', '', $phone)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:vehicle,property,rental',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:3',
            'brand_id' => 'required_if:type,vehicle',
            'model_id' => 'required_if:type,vehicle',
            'year' => 'required_if:type,vehicle|integer',
            'mileage' => 'nullable|integer',
            'fuel_type' => 'nullable|string',
            'transmission' => 'nullable|string',
            'condition' => 'nullable|string',
            'property_type_id' => 'required_if:type,property',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'area_sqm' => 'nullable|numeric',
            'furnishing' => 'nullable|string',
            'rental_type' => 'required_if:type,rental|string',
        ]);
        return DB::transaction(function () use ($validated, $request) {
            $listable = match ($validated['type']) {
                'vehicle' => Vehicle::create([
                    'brand_id' => $validated['brand_id'], 'model_id' => $validated['model_id'],
                    'year' => $validated['year'], 'mileage' => $validated['mileage'] ?? null,
                    'fuel_type' => $validated['fuel_type'] ?? null,
                    'transmission' => $validated['transmission'] ?? null,
                    'condition' => $validated['condition'] ?? 'used',
                ]),
                'property' => Property::create([
                    'property_type_id' => $validated['property_type_id'], 'title' => $validated['title'],
                    'bedrooms' => $validated['bedrooms'] ?? null, 'bathrooms' => $validated['bathrooms'] ?? null,
                    'area_sqm' => $validated['area_sqm'] ?? null, 'furnishing' => $validated['furnishing'] ?? null,
                ]),
                'rental' => Rental::create([
                    'title' => $validated['title'], 'rental_type' => $validated['rental_type'] ?? 'monthly',
                    'bedrooms' => $validated['bedrooms'] ?? null,
                ]),
            };
            $listing = Listing::create([
                'user_id' => $request->user()->id, 'type' => $validated['type'],
                'listable_type' => get_class($listable), 'listable_id' => $listable->id,
                'title' => $validated['title'], 'description' => $validated['description'] ?? null,
                'price' => $validated['price'], 'currency' => $validated['currency'] ?? 'GBP',
                'city' => $validated['city'] ?? null, 'country' => $validated['country'] ?? null, 'status' => 'pending',
            ]);
            PriceHistory::create(['listing_id' => $listing->id, 'price' => $listing->price, 'currency' => $listing->currency, 'changed_by' => 'seller']);
            \App\Support\StaffAlerts::send('listing', "New listing to review: {$listing->title}", $listing->description,
                \App\Support\StaffAlerts::person($request->user()), "/admin/listings/{$listing->id}/edit",
                ['Type' => $listing->type, 'Price' => number_format((float) $listing->price).' '.$listing->currency, 'City' => $listing->city]);
            return response()->json(['listing' => $listing->fresh(['listable']), 'message' => 'Submitted for review.'], 201);
        });
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $listing = Listing::where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $request->validate(['title' => 'sometimes|string', 'description' => 'nullable|string', 'price' => 'sometimes|numeric']);
        if (isset($validated['price']) && $validated['price'] != $listing->price) {
            PriceHistory::create(['listing_id' => $listing->id, 'price' => $validated['price'], 'currency' => $listing->currency, 'changed_by' => 'seller']);
        }
        $listing->update($validated);
        return response()->json(['listing' => $listing->fresh(), 'message' => 'Updated.']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        Listing::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function toggleFavorite(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;
        $fav = ListingFavorite::where('user_id', $userId)->where('listing_id', $id)->first();
        if ($fav) { $fav->delete(); Listing::find($id)?->decrement('favorite_count'); return response()->json(['favorited' => false]); }
        ListingFavorite::create(['user_id' => $userId, 'listing_id' => $id]);
        Listing::find($id)?->increment('favorite_count');
        return response()->json(['favorited' => true]);
    }

    public function myListings(Request $request): JsonResponse
    {
        return response()->json(Listing::with('listable')->where('user_id', $request->user()->id)->orderByDesc('created_at')->paginate(10));
    }
}
