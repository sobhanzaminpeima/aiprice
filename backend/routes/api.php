<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ListingManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\ListingExtractionController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
// Global ceiling against scraping/abuse; tighter per-route limits still apply.
Route::prefix('v1')->middleware('throttle:api-global')->group(function () {
    // Auth
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    // Signed link from the verification email; the signature is relative so it
    // survives the SPA proxy forwarding it from a different host.
    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed:relative', 'throttle:6,1'])
        ->name('verification.verify');

    // Public listings
    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/featured', [ListingController::class, 'featured']);
    Route::get('/listings/{slug}', [ListingController::class, 'show']);
    // Lightweight marker payload for the map view — never the full listing object.
    Route::get('/listings-map', function (Request $req) {
        $listings = \App\Models\Listing::where('status', 'active')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->when($req->type, fn ($q) => $q->where('type', $req->type))
            ->get(['id', 'slug', 'title', 'type', 'price', 'currency', 'city', 'latitude', 'longitude', 'thumbnail', 'price_battery_percent', 'ai_price_status']);
        return response()->json([
            'listings' => $listings,
            'bounds' => \App\Services\GeoService::BOUNDS,
            'center' => \App\Services\GeoService::CENTER,
        ]);
    });
    // "Show Me Better" — real alternatives with grounded, comparable-data reasons.
    Route::get('/listings/{id}/better', function (string $id) {
        $listing = \App\Models\Listing::where('status', 'active')->findOrFail($id);
        $alternatives = app(\App\Services\AI\AlternativesService::class)->findBetter($listing);
        return response()->json(['alternatives' => $alternatives]);
    });

    // Vehicle brands/models
    Route::get('/vehicle-brands', fn() => response()->json(\App\Models\VehicleBrand::where('is_active', true)->with('models')->orderBy('name')->get()));
    Route::get('/property-types', fn() => response()->json(\App\Models\PropertyType::where('is_active', true)->orderBy('name')->get()));

    // Billing: provider webhooks are public and authenticated by signature.
    Route::get('/billing/status', [\App\Http\Controllers\Api\BillingController::class, 'status']);
    Route::post('/webhooks/payments', \App\Http\Controllers\Api\PaymentWebhookController::class)->middleware('throttle:120,1');

    // Public contact form → guest support ticket
    Route::post('/contact', [\App\Http\Controllers\Api\SupportTicketController::class, 'contact'])->middleware('throttle:5,60');

    // Public blog + FAQ
    Route::get('/blog', [\App\Http\Controllers\Api\ContentController::class, 'posts']);
    Route::get('/blog/categories', [\App\Http\Controllers\Api\ContentController::class, 'categories']);
    Route::get('/blog/{slug}', [\App\Http\Controllers\Api\ContentController::class, 'post']);
    Route::get('/faqs', [\App\Http\Controllers\Api\ContentController::class, 'faqs']);
    Route::get('/subscription-plans', fn() => response()->json(\App\Models\SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get()));

    // AI chat assistant — public, throttled (grounded in real DB search, see ChatService)
    Route::post('/chat', function (Request $req) {
        $req->validate(['message' => 'required|string|max:500']);
        $chat = app(\App\Services\AI\ChatService::class);
        return response()->json($chat->respond($req->message));
    })->middleware('throttle:20,1');

    // Market stats (public) — field names match what HomeView.vue's stats bar reads.
    Route::get('/market/stats', function () {
        $activeListings = \App\Models\Listing::where('status', 'active')->count();
        return response()->json([
            'total_listings' => $activeListings,
            'active_listings' => $activeListings,
            'vehicle_listings' => \App\Models\Listing::where('type', 'vehicle')->where('status', 'active')->count(),
            'property_listings' => \App\Models\Listing::where('type', 'property')->where('status', 'active')->count(),
            'rental_listings' => \App\Models\Listing::where('type', 'rental')->where('status', 'active')->count(),
            'ai_analyses_today' => \App\Models\Listing::whereDate('ai_scored_at', today())->count(),
            'ai_scored_listings' => \App\Models\Listing::where('status', 'active')->whereNotNull('ai_scored_at')->count(),
            // Public, aggregate-only market indexes — same maths as the admin view.
            'indexes' => \App\Models\Listing::where('status', 'active')
                ->selectRaw('type, COUNT(*) as listings, AVG(ai_trust_score) as trust, AVG(ai_demand_score) as demand, AVG(ai_deal_score) as deal')
                ->groupBy('type')->get()
                ->map(function ($r) {
                    $parts = array_values(array_filter([$r->trust, $r->demand, $r->deal], fn ($v) => $v !== null));
                    return [
                        'type' => $r->type, 'listings' => (int) $r->listings,
                        'index' => $parts ? (int) round(array_sum($parts) / count($parts)) : null,
                        'trust' => $r->trust !== null ? (int) round($r->trust) : null,
                    ];
                }),
            'price_verdicts' => \App\Models\Listing::where('status', 'active')->whereNotNull('ai_price_status')
                ->selectRaw('ai_price_status, COUNT(*) as n')->groupBy('ai_price_status')->pluck('n', 'ai_price_status'),
        ]);
    })->middleware('throttle:60,1');

    // Authenticated user routes
    Route::middleware(['auth:sanctum', 'password.fresh'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');

        // AI Preference Graph — "welcome back" memory. Save/read/delete only,
        // no inference beyond what the user actually searched for.
        Route::post('/me/search-preferences', function (Request $req) {
            $req->validate([
                'asset_type' => 'required|in:vehicle,property,rental',
                'budget_max' => 'nullable|numeric',
                'city' => 'nullable|string',
                'priorities' => 'nullable|array',
            ]);
            $pref = app(\App\Services\AI\PreferenceService::class)->remember($req->user(), $req->all(), 'intent_composer');
            return response()->json($pref, 201);
        });
        Route::get('/me/search-preferences/last', function (Request $req) {
            $service = app(\App\Services\AI\PreferenceService::class);
            $pref = $service->last($req->user());
            if (!$pref) {
                return response()->json(['preference' => null]);
            }
            return response()->json([
                'preference' => $pref,
                'new_matches' => $service->newMatchesSince($pref),
            ]);
        });
        Route::delete('/me/search-preferences/{id}', function (Request $req, string $id) {
            app(\App\Services\AI\PreferenceService::class)->forget($req->user(), $id);
            return response()->json(['message' => 'Deleted.']);
        });

        // Listings CRUD
        // Publishing needs a verified email so the marketplace isn't open to throwaway accounts.
        Route::post('/listings', [ListingController::class, 'store'])->middleware('verified');
        // AI draft: turns free text into a pre-filled listing form. Never
        // creates a listing itself — the seller still reviews and submits.
        Route::post('/listings/extract', [ListingExtractionController::class, 'extract'])->middleware('throttle:10,1');
        Route::put('/listings/{id}', [ListingController::class, 'update']);
        Route::delete('/listings/{id}', [ListingController::class, 'destroy']);
        Route::post('/listings/{id}/favorite', [ListingController::class, 'toggleFavorite']);
        Route::get('/my-listings', [ListingController::class, 'myListings']);
        // Full seller phone: signed-in users only, rate limited against scraping.
        Route::get('/listings/{id}/seller-phone', [ListingController::class, 'sellerPhone'])->middleware('throttle:30,60');
        // Messages received on the seller's own listings. The buyer's contact
        // details are included only when the buyer opted in (share_contact).
        Route::get('/my-listing-inquiries', function (Request $req) {
            return response()->json(
                \App\Models\Inquiry::whereHas('listing', fn($q) => $q->where('user_id', $req->user()->id))
                    ->with(['listing:id,title,slug', 'buyer:id,name,email,phone'])
                    ->orderByDesc('created_at')
                    ->limit(200)
                    ->get(['id', 'listing_id', 'buyer_id', 'message', 'share_contact', 'status', 'created_at'])
                    ->map(function ($i) {
                        $row = $i->only(['id', 'listing_id', 'message', 'share_contact', 'status', 'created_at']);
                        $row['listing'] = $i->listing;
                        $row['buyer'] = $i->share_contact && $i->buyer
                            ? ['name' => $i->buyer->name, 'email' => $i->buyer->email, 'phone' => $i->buyer->phone]
                            : null;
                        return $row;
                    })
            );
        });

        // In-app notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Support tickets (customer side)
        Route::get('/support-tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'index']);
        Route::post('/support-tickets', [\App\Http\Controllers\Api\SupportTicketController::class, 'store'])->middleware('throttle:5,60');
        Route::get('/support-tickets/{id}', [\App\Http\Controllers\Api\SupportTicketController::class, 'show']);
        Route::post('/support-tickets/{id}/reply', [\App\Http\Controllers\Api\SupportTicketController::class, 'reply'])->middleware('throttle:30,60');

        // KYC verification
        Route::get('/kyc', [KycController::class, 'show']);
        Route::post('/kyc', [KycController::class, 'store']);
        Route::get('/kyc/{id}/{field}', [KycController::class, 'document']);

        // Subscription
        Route::get('/my-subscription', [SubscriptionController::class, 'mine']);
        Route::get('/my-subscription/history', [SubscriptionController::class, 'history']);
        Route::post('/my-subscription/{id}/cancel', [SubscriptionController::class, 'cancel']);

        // Billing
        Route::post('/billing/checkout', [\App\Http\Controllers\Api\BillingController::class, 'checkout'])->middleware(['verified', 'throttle:10,60']);
        Route::get('/billing/invoices', [\App\Http\Controllers\Api\BillingController::class, 'invoices']);
        Route::get('/billing/invoices/{id}', [\App\Http\Controllers\Api\BillingController::class, 'invoice']);

        // User profile
        Route::get('/profile', fn(Request $req) => response()->json($req->user()->load('profile')));
        Route::put('/profile', function (Request $req) {
            $v = $req->validate(['name' => 'sometimes|string', 'phone' => 'nullable|string']);
            $req->user()->update($v);
            return response()->json($req->user()->fresh());
        });

        // Favorites
        Route::get('/favorites', fn(Request $req) => response()->json(
            \App\Models\ListingFavorite::where('user_id', $req->user()->id)->with('listing.listable')->paginate(12)
        ));

        // Conversations/Messages
        Route::get('/conversations', function (Request $req) {
            return response()->json(
                \App\Models\Conversation::where('buyer_id', $req->user()->id)
                    ->orWhere('seller_id', $req->user()->id)
                    ->with(['buyer:id,name,avatar', 'seller:id,name,avatar', 'listing:id,title,slug'])
                    ->orderByDesc('last_message_at')->paginate(20)
            );
        });

        Route::get('/conversations/{id}/messages', function (Request $req, string $id) {
            $conv = \App\Models\Conversation::where(function ($q) use ($req) {
                $q->where('buyer_id', $req->user()->id)->orWhere('seller_id', $req->user()->id);
            })->findOrFail($id);
            return response()->json(\App\Models\Message::where('conversation_id', $conv->id)->with('sender:id,name,avatar')->orderBy('created_at')->paginate(50));
        });

        Route::post('/conversations/{id}/messages', function (Request $req, string $id) {
            $req->validate(['body' => 'required|string|max:2000']);
            $conv = \App\Models\Conversation::where(function ($q) use ($req) {
                $q->where('buyer_id', $req->user()->id)->orWhere('seller_id', $req->user()->id);
            })->findOrFail($id);
            $msg = \App\Models\Message::create(['conversation_id' => $conv->id, 'sender_id' => $req->user()->id, 'body' => $req->body]);
            $conv->update(['last_message_at' => now()]);
            return response()->json($msg, 201);
        });

        // "Send to seller": the message goes to the seller's inbox (in-app +
        // email). Staff are notified too so they can moderate abuse. The
        // buyer's contact details reach the seller only if share_contact=true.
        Route::post('/listings/{id}/inquire', function (Request $req, string $id) {
            $data = $req->validate([
                'message' => 'required|string|min:5|max:2000',
                'share_contact' => 'sometimes|boolean',
            ]);
            $listing = \App\Models\Listing::where('status', 'active')->findOrFail($id);
            if ($listing->user_id === $req->user()->id) {
                return response()->json(['message' => 'You cannot message yourself about your own listing.'], 422);
            }
            $inquiry = \App\Models\Inquiry::create([
                'listing_id' => $id,
                'buyer_id' => $req->user()->id,
                'message' => strip_tags($data['message']),
                'share_contact' => (bool) ($data['share_contact'] ?? false),
            ]);
            $listing->increment('inquiry_count');
            if ($listing->user && $listing->user->is_active) {
                $listing->user->notify(new \App\Notifications\NewInquiryForSeller($inquiry->load('listing')));
            }

            $staff = \App\Models\User::permission('manage_support')->where('is_active', true)->get()
                ->merge(\App\Models\User::role('super_admin')->where('is_active', true)->get())
                ->unique('id');
            \Illuminate\Support\Facades\Notification::send($staff, new \App\Notifications\NewInquiryForStaff($inquiry->load('listing')));

            return response()->json(['message' => 'Your message was sent to the seller.', 'inquiry' => $inquiry->only(['id', 'status', 'created_at'])], 201);
        })->middleware(['verified', 'throttle:10,60']);

        // Buyer's own sent inquiries — listing info only, never the owner's identity.
        Route::get('/my-inquiries', function (Request $req) {
            return response()->json(
                \App\Models\Inquiry::where('buyer_id', $req->user()->id)
                    ->with('listing:id,title,slug,price,currency,thumbnail')
                    ->orderByDesc('created_at')->paginate(20)
            );
        });

        // Fraud report
        Route::post('/listings/{id}/report', function (Request $req, string $id) {
            $req->validate(['reason' => 'required|string|max:100', 'description' => 'nullable|string|max:2000']);
            $listing = \App\Models\Listing::findOrFail($id);

            abort_if($listing->user_id === $req->user()->id, 422, 'You cannot report your own listing.');

            $existing = \App\Models\FraudReport::where('listing_id', $id)->where('reported_by', $req->user()->id)
                ->whereIn('status', ['pending', 'investigating'])->first();
            if ($existing) {
                return response()->json(['message' => 'You already reported this listing — our team is looking at it.', 'report' => $existing]);
            }

            $risk = app(\App\Services\FraudSignals::class)->assess($listing);
            $report = \App\Models\FraudReport::create([
                'listing_id' => $id, 'reported_by' => $req->user()->id, 'reason' => $req->reason,
                'description' => $req->description, 'status' => 'pending',
                // Snapshot why the listing looked risky at report time.
                'risk_score' => $risk['score'], 'ai_analysis' => ['signals' => $risk['signals']],
            ]);
            return response()->json(['message' => 'Report submitted.', 'report' => $report], 201);
        })->middleware('throttle:10,60');

        // Create listing with brand/model name resolution
        Route::post('/listings/create', function (Request $req) {
            $req->validate([
                'type' => 'required|in:vehicle,property,rental',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'city' => 'nullable|string',
                'district' => 'nullable|string',
                'brand_name' => 'required_if:type,vehicle|string',
                'model_name' => 'required_if:type,vehicle|string',
                'year' => 'required_if:type,vehicle|integer',
                'mileage' => 'nullable|integer',
                'fuel_type' => 'nullable|string',
                'transmission' => 'nullable|string',
                'body_type' => 'nullable|string',
                'color' => 'nullable|string',
                'engine_size' => 'nullable|string',
                'condition' => 'nullable|string',
                'property_type_name' => 'required_if:type,property|string',
                'listing_for' => 'nullable|string',
                'bedrooms' => 'nullable|integer',
                'bathrooms' => 'nullable|integer',
                'area_sqm' => 'nullable|numeric',
                'build_year' => 'nullable|integer',
                'has_parking' => 'nullable|boolean',
                'has_garden' => 'nullable|boolean',
                'has_pool' => 'nullable|boolean',
                'has_elevator' => 'nullable|boolean',
                'price_negotiable' => 'nullable|boolean',
                'video_url' => 'nullable|url',
            ]);
            return \Illuminate\Support\Facades\DB::transaction(function () use ($req) {
                $listable = null;
                if ($req->type === 'vehicle') {
                    $brand = \App\Models\VehicleBrand::firstOrCreate(['name' => $req->brand_name], ['is_active' => true]);
                    $model = \App\Models\VehicleModel::firstOrCreate(['brand_id' => $brand->id, 'name' => $req->model_name], ['is_active' => true]);
                    $listable = \App\Models\Vehicle::create([
                        'brand_id' => $brand->id, 'model_id' => $model->id,
                        'year' => $req->year, 'mileage' => $req->mileage,
                        'fuel_type' => $req->fuel_type, 'transmission' => $req->transmission,
                        'body_type' => $req->body_type, 'color' => $req->color,
                        'engine_size' => $req->engine_size, 'condition' => $req->condition ?? 'Good',
                    ]);
                } elseif ($req->type === 'property') {
                    $propType = \App\Models\PropertyType::firstOrCreate(['name' => $req->property_type_name], ['is_active' => true]);
                    $listable = \App\Models\Property::create([
                        'property_type_id' => $propType->id, 'title' => $req->title,
                        'bedrooms' => $req->bedrooms, 'bathrooms' => $req->bathrooms,
                        'area_sqm' => $req->area_sqm, 'build_year' => $req->build_year,
                        'has_parking' => (bool)$req->has_parking, 'has_garden' => (bool)$req->has_garden,
                        'has_pool' => (bool)$req->has_pool, 'has_elevator' => (bool)$req->has_elevator,
                        'listing_for' => $req->listing_for ?? 'sale',
                    ]);
                } else {
                    $listable = \App\Models\Rental::create(['title' => $req->title, 'rental_type' => 'monthly', 'bedrooms' => $req->bedrooms]);
                }
                $listing = \App\Models\Listing::create([
                    'user_id' => $req->user()->id, 'type' => $req->type,
                    'listable_type' => get_class($listable), 'listable_id' => $listable->id,
                    'title' => $req->title, 'description' => $req->description,
                    'price' => $req->price, 'currency' => $req->currency ?? 'GBP',
                    'city' => $req->city, 'district' => $req->district,
                    'price_negotiable' => (bool)$req->price_negotiable,
                    'video_url' => $req->video_url, 'status' => 'pending',
                ]);
                \App\Models\PriceHistory::create(['listing_id' => $listing->id, 'price' => $listing->price, 'currency' => $listing->currency, 'changed_by' => 'seller']);
                return response()->json(['listing' => $listing->fresh(['listable']), 'message' => 'Submitted for review.'], 201);
            });
        })->middleware('verified');

        // Upload images for a listing (multipart)
        Route::post('/listings/{id}/upload-images', function (Request $req, string $id) {
            $listing = \App\Models\Listing::where('user_id', $req->user()->id)->findOrFail($id);
            $req->validate(['images' => 'required|array|min:1', 'images.*' => 'image|max:10240']);
            $urls = [];
            foreach ($req->file('images') as $file) {
                $path = $file->store('listings/'.$id, 'public');
                $urls[] = '/storage/'.$path;
            }
            $existing = $listing->images ?? [];
            if (is_string($existing)) $existing = json_decode($existing, true) ?? [];
            $all = array_merge($existing, $urls);
            $listing->update(['images' => $all, 'thumbnail' => $all[0] ?? $listing->thumbnail]);
            return response()->json(['images' => $all, 'message' => count($urls).' images uploaded.']);
        });

        // AI re-analyze price (can be called multiple times)
        Route::post('/listings/{id}/ai-analyze', function (Request $req, string $id) {
            $listing = \App\Models\Listing::where('user_id', $req->user()->id)->findOrFail($id);
            if ($req->price) $listing->update(['price' => $req->price]);
            \App\Jobs\AnalyzeListingPrice::dispatch($listing->id);
            return response()->json(['message' => 'Analysis queued.', 'listing' => $listing->fresh()], 202);
        })->middleware('throttle:10,60');

        // Public AI price estimate (before listing created) — real comparable-based
        // price intelligence only. Trust/invest/demand require an actual listing
        // history and are averaged from real comparable listings, never randomized.
        Route::post('/ai/price-estimate', function (Request $req) {
            $data = $req->validate([
                'type' => 'required|in:vehicle,property,rental',
                'price' => 'required|numeric|min:1|max:1000000000',
                'currency' => 'sometimes|string|in:GBP,EUR,USD,TRY',
                'city' => 'nullable|string|max:100',
                'brand_id' => 'nullable|string|max:64', 'model_id' => 'nullable|string|max:64',
                'year' => 'nullable|integer|min:1950|max:2100',
                'property_type_id' => 'nullable|string|max:64',
                'bedrooms' => 'nullable|integer|min:0|max:50', 'area_sqm' => 'nullable|numeric|min:1|max:100000',
                'brand_name' => 'nullable|string|max:100', 'model_name' => 'nullable|string|max:100',
                'property_type_name' => 'nullable|string|max:100',
            ]);
            // The listing form sends names; resolve them to catalogue ids.
            if (empty($data['brand_id']) && ! empty($data['brand_name'])) {
                $data['brand_id'] = \App\Models\VehicleBrand::where('name', $data['brand_name'])->value('id');
            }
            if (empty($data['model_id']) && ! empty($data['model_name']) && ! empty($data['brand_id'])) {
                $data['model_id'] = \App\Models\VehicleModel::where('brand_id', $data['brand_id'])->where('name', $data['model_name'])->value('id');
            }
            if (empty($data['property_type_id']) && ! empty($data['property_type_name'])) {
                $data['property_type_id'] = \App\Models\PropertyType::where('name', $data['property_type_name'])->value('id');
            }
            $traits = array_filter([
                'brand_id' => $data['brand_id'] ?? null, 'model_id' => $data['model_id'] ?? null, 'year' => $data['year'] ?? null,
                'brand_name' => isset($data['brand_id']) ? mb_strtolower((string) \App\Models\VehicleBrand::whereKey($data['brand_id'])->value('name')) : null,
                'model_name' => isset($data['model_id']) ? mb_strtolower((string) \App\Models\VehicleModel::whereKey($data['model_id'])->value('name')) : null,
                'property_type_id' => $data['property_type_id'] ?? null, 'bedrooms' => $data['bedrooms'] ?? null, 'area_sqm' => $data['area_sqm'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $priceService = app(\App\Services\AI\PriceIntelligenceService::class);
            $result = $priceService->estimate($data['type'], (float) $data['price'], $data['city'] ?? null, $data['currency'] ?? 'GBP', $traits);

            $comparableIds = $result['comparable_ids'] ?? [];
            $avgScores = $comparableIds
                ? \App\Models\Listing::whereIn('id', $comparableIds)
                    ->selectRaw('AVG(ai_trust_score) as trust, AVG(ai_investment_score) as invest, AVG(ai_demand_score) as demand')
                    ->first()
                : null;

            return response()->json([
                'has_estimate' => $result['has_estimate'],
                'price_status' => $result['price_status'],
                'battery_score' => $result['has_estimate'] ? $result['battery_score'] : null,
                'battery_label' => $result['battery_label'],
                'market_avg' => $result['market_avg'],
                'market_min' => $result['market_min'],
                'market_max' => $result['market_max'],
                'comparables' => $result['comparable_count'],
                'confidence' => $result['confidence'],
                'reasons' => $result['reasons'],
                // Null (not fabricated) when no comparable listings have been scored yet.
                'trust_score' => $avgScores?->trust ? (int) round($avgScores->trust) : null,
                'invest_score' => $avgScores?->invest ? (int) round($avgScores->invest) : null,
                'demand_score' => $avgScores?->demand ? (int) round($avgScores->demand) : null,
                'disclaimer' => 'Estimate based on comparable asking prices. Not a valuation or a guarantee.',
            ]);
        })->middleware('throttle:30,1');
    });

    // Public advertisements (for homepage)
    Route::get('/advertisements', function (Request $req) {
        return response()->json(\App\Models\Advertisement::where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($req->slot, fn($q) => $q->where('slot', $req->slot))
            ->when($req->type, fn($q) => $q->where('type', $req->type))
            ->orderByDesc('created_at')->get());
    });

    // Track ad click
    Route::post('/advertisements/{id}/click', function (string $id) {
        \App\Models\Advertisement::find($id)?->increment('click_count');
        return response()->json(['ok' => true]);
    });

    // Admin routes
    Route::middleware(['auth:sanctum', 'password.fresh', 'admin.permission'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'overview']);

        // Listing management
        Route::get('/listings', [ListingManagementController::class, 'index']);
        Route::post('/listings/{id}/approve', [ListingManagementController::class, 'approve']);
        Route::post('/listings/{id}/reject', [ListingManagementController::class, 'reject']);
        Route::post('/listings/{id}/feature', [ListingManagementController::class, 'feature']);
        Route::delete('/listings/{id}', [ListingManagementController::class, 'destroy']);
        Route::post('/listings/{id}/reanalyze', [ListingManagementController::class, 'reanalyze']);

        // Buyer inquiries — the ONLY place both the buyer's and the owner's
        // real contact details appear together. Admin reviews and relays
        // contact manually; neither party ever sees the other's identity.
        Route::get('/inquiries', function (Request $req) {
            $q = \App\Models\Inquiry::with([
                    'buyer:id,name,email,phone',
                    'listing:id,title,slug,price,currency,user_id',
                    'listing.user:id,name,email,phone',
                ])
                ->when($req->status, fn($q) => $q->where('status', $req->status))
                ->orderByDesc('created_at');
            return response()->json($q->paginate(20));
        });
        Route::put('/inquiries/{id}', function (Request $req, string $id) {
            $inquiry = \App\Models\Inquiry::findOrFail($id);
            $v = $req->validate(['status' => 'sometimes|in:pending,contacted,closed', 'admin_notes' => 'nullable|string']);
            $inquiry->update($v);
            return response()->json($inquiry);
        });

        // User management
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::post('/users/{id}/toggle-active', [UserManagementController::class, 'toggleActive']);
        Route::put('/users/{id}/role', [UserManagementController::class, 'updateRole']);
        Route::post('/users/{id}/approve-kyc', [UserManagementController::class, 'approveKyc']);
        Route::post('/users/{id}/reject-kyc', [UserManagementController::class, 'rejectKyc']);
        Route::get('/kyc', [UserManagementController::class, 'kycQueue']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
        Route::get('/users/{id}/tokens', function (string $id) {
            $user = \App\Models\User::findOrFail($id);
            return response()->json($user->tokens()->select('id', 'name', 'abilities', 'last_used_at', 'created_at')->orderByDesc('created_at')->get());
        });
        Route::delete('/users/{id}/tokens/{tokenId}', function (string $id, string $tokenId) {
            $user = \App\Models\User::findOrFail($id);
            $user->tokens()->where('id', $tokenId)->delete();
            return response()->json(['message' => 'Token revoked.']);
        });

        // Vehicle brands/models CRUD
        Route::apiResource('vehicle-brands', \App\Http\Controllers\Admin\VehicleBrandController::class);
        Route::apiResource('vehicle-models', \App\Http\Controllers\Admin\VehicleModelController::class);
        Route::apiResource('property-types', \App\Http\Controllers\Admin\PropertyTypeController::class);

        // Competitor sources
        Route::get('/competitor-sources', fn() => response()->json(\App\Models\CompetitorSource::all()));
        Route::post('/competitor-sources', function (Request $req) {
            $v = $req->validate(['name' => 'required|string', 'url' => 'required|url', 'type' => 'required|in:vehicle,property,rental']);
            return response()->json(\App\Models\CompetitorSource::create($v + ['is_active' => true]), 201);
        });
        Route::put('/competitor-sources/{id}', function (Request $req, string $id) {
            $src = \App\Models\CompetitorSource::findOrFail($id);
            $src->update($req->only(['name', 'url', 'type', 'is_active', 'scrape_interval']));
            return response()->json($src);
        });
        Route::delete('/competitor-sources/{id}', function (string $id) {
            \App\Models\CompetitorSource::findOrFail($id)->delete();
            return response()->json(['message' => 'Deleted.']);
        });
        // Trigger import for a source
        Route::post('/competitor-sources/{id}/import', function (string $id) {
            $source = \App\Models\CompetitorSource::findOrFail($id);
            \App\Jobs\CrawlCompetitorSource::dispatch($source->id);
            return response()->json(['queued' => 1, 'message' => "Crawl of {$source->name} queued."], 202);
        });
        // Import all sources
        Route::post('/competitor-sources/import-all', function () {
            $ids = \App\Models\CompetitorSource::where('is_active', true)->pluck('id');
            $ids->each(fn ($id) => \App\Jobs\CrawlCompetitorSource::dispatch($id));
            return response()->json(['queued' => $ids->count(), 'message' => "{$ids->count()} crawls queued."], 202);
        });
        // View competitor listings (admin only, never public)
        Route::get('/competitor-listings', function (Request $req) {
            $q = \App\Models\CompetitorListing::with('source:id,name,url')
                ->when($req->source_id, fn($q) => $q->where('source_id', $req->source_id))
                ->when($req->type, fn($q) => $q->where('type', $req->type))
                ->when($req->city, fn($q) => $q->where('city', $req->city))
                ->orderByDesc('created_at')
                ->paginate(25);
            return response()->json($q);
        });
        // Stats per source
        Route::get('/competitor-sources/{id}/stats', function (string $id) {
            $source = \App\Models\CompetitorSource::findOrFail($id);
            $listings = \App\Models\CompetitorListing::where('source_id', $id);
            return response()->json([
                'source' => $source,
                'total_listings' => $listings->count(),
                'by_type' => $listings->selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
                'avg_price' => $listings->avg('price'),
                'min_price' => $listings->min('price'),
                'max_price' => $listings->max('price'),
                'last_import' => $source->last_scraped_at,
            ]);
        });

        // AI Market Insights — real aggregates from listings/scores (no mock data)
        Route::get('/ai-insights', function () {
            $insights = app(\App\Services\AI\MarketInsightsService::class);
            return response()->json([
                'market_trust' => $insights->marketTrust(),
                'undervalued' => $insights->undervalued(),
                'overpriced' => $insights->overpriced(),
                'demand_by_category' => $insights->demandByCategory(),
                'fast_selling' => $insights->fastSelling(),
            ]);
        });

        // AI Models management
        Route::get('/ai-models', fn() => response()->json(\App\Models\AiModel::orderByDesc('created_at')->get()));
        Route::post('/ai-models', function (Request $req) {
            $v = $req->validate([
                'name' => 'required|string|max:255',
                'provider' => 'required|in:openai,nvidia,anthropic,gemini',
                'model_id' => 'required|string|max:255',
                'purpose' => 'required|string|max:255',
                'is_active' => 'boolean',
                'is_default' => 'boolean',
                'temperature' => 'nullable|numeric|min:0|max:2',
                'max_tokens' => 'nullable|integer|min:1',
            ]);
            $model = \App\Models\AiModel::create($v + ['temperature' => $v['temperature'] ?? 0.7, 'max_tokens' => $v['max_tokens'] ?? 2000]);
            return response()->json($model, 201);
        });
        Route::put('/ai-models/{id}', function (Request $req, string $id) {
            $model = \App\Models\AiModel::findOrFail($id);
            $model->update($req->only(['is_active', 'is_default', 'temperature', 'max_tokens', 'monthly_cost_limit', 'rate_limit_per_minute']));
            return response()->json($model);
        });
        Route::delete('/ai-models/{id}', function (string $id) {
            \App\Models\AiModel::findOrFail($id)->delete();
            return response()->json(['message' => 'Model removed.']);
        });

        // LLM Management — providers configured via env; this just proves the
        // configured key for the chosen provider can actually reach the API.
        // Honest health view of the AI price check for the admin "AI Status" page.
        Route::get('/ai/status', function () {
            $active = \App\Models\Listing::where('status', 'active');
            return response()->json([
                'openai_configured' => filled(config('services.openai.key')),
                'openai_model' => config('services.openai.model', 'gpt-4o-mini'),
                'queue_connection' => config('queue.default'),
                'active_listings' => (clone $active)->count(),
                'with_estimate' => (clone $active)->whereNotNull('price_battery_percent')->count(),
                'insufficient_data' => (clone $active)->where('ai_price_status', 'insufficient_data')->count(),
                'never_scored' => (clone $active)->whereNull('ai_scored_at')->count(),
                'last_scored_at' => (clone $active)->max('ai_scored_at'),
                'pending_jobs' => \Illuminate\Support\Facades\Schema::hasTable('jobs') ? \Illuminate\Support\Facades\DB::table('jobs')->count() : null,
                'failed_jobs' => \Illuminate\Support\Facades\Schema::hasTable('failed_jobs') ? \Illuminate\Support\Facades\DB::table('failed_jobs')->count() : null,
                'market_rows' => \App\Models\CompetitorListing::where('is_active', true)->count(),
                'market_sources' => \App\Models\CompetitorSource::get(['name', 'is_active', 'last_scraped_at']),
            ]);
        });
        Route::get('/llm/providers', function () {
            return response()->json([
                ['provider' => 'openai', 'configured' => !empty(config('services.openai.key')), 'model' => config('services.openai.model')],
                ['provider' => 'nvidia', 'configured' => !empty(config('services.nvidia.key')), 'model' => config('services.nvidia.model')],
            ]);
        });
        Route::post('/llm/test', function (Request $req) {
            $req->validate(['prompt' => 'required|string|max:500', 'provider' => 'nullable|in:openai,nvidia']);
            $provider = $req->input('provider', 'openai');
            if ($provider === 'nvidia') {
                $apiKey = config('services.nvidia.key', '');
                $baseUrl = rtrim(config('services.nvidia.base_url', 'https://integrate.api.nvidia.com/v1'), '/');
                $model = $req->input('model', config('services.nvidia.model', 'meta/llama-3.2-11b-vision-instruct'));
            } else {
                $apiKey = config('services.openai.key', '');
                $baseUrl = 'https://api.openai.com/v1';
                $model = $req->input('model', config('services.openai.model', 'gpt-4o-mini'));
            }
            if (empty($apiKey)) {
                return response()->json(['error' => ucfirst($provider) . ' API key not configured.'], 422);
            }
            $resp = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->timeout(20)->post($baseUrl . '/chat/completions', [
                'model'       => $model,
                'messages'    => [['role' => 'user', 'content' => $req->prompt]],
                'temperature' => 0.7,
                'max_tokens'  => 200,
            ]);
            if (!$resp->successful()) {
                return response()->json(['error' => ucfirst($provider) . ' error: ' . $resp->status() . ' — ' . $resp->body()], 422);
            }
            return response()->json(['response' => $resp->json('choices.0.message.content')]);
        });

        // Batch AI analyze pending listings
        // ?all=1 re-scores every active listing (needed after the pricing
        // model changes); otherwise only listings never scored.
        Route::post('/listings/batch-analyze', function (Request $req) {
            $ids = \App\Models\Listing::where('status', 'active')
                ->when(! $req->boolean('all'), fn ($q) => $q->whereNull('ai_scored_at'))
                ->limit(2000)
                ->pluck('id');
            $ids->each(fn ($id) => \App\Jobs\AnalyzeListingPrice::dispatch($id));
            return response()->json(['queued' => $ids->count(), 'message' => "Queued {$ids->count()} listings for analysis."], 202);
        });

        // Social / Instagram auto-publishing
        Route::get('/social/posts', [\App\Http\Controllers\Admin\SocialController::class, 'index']);
        Route::post('/social/generate', [\App\Http\Controllers\Admin\SocialController::class, 'generate']);
        Route::post('/social/generate-batch', [\App\Http\Controllers\Admin\SocialController::class, 'generateBatch']);
        Route::put('/social/posts/{socialPost}', [\App\Http\Controllers\Admin\SocialController::class, 'update']);
        Route::post('/social/posts/{socialPost}/publish', [\App\Http\Controllers\Admin\SocialController::class, 'publish']);
        Route::delete('/social/posts/{socialPost}', [\App\Http\Controllers\Admin\SocialController::class, 'destroy']);
        Route::get('/social/test', [\App\Http\Controllers\Admin\SocialController::class, 'testConnection']);

        // Settings
        Route::get('/settings', fn() => response()->json(\App\Models\Setting::all()));
        Route::put('/settings/{key}', function (Request $req, string $key) {
            $req->validate(['value' => 'required']);
            \App\Models\Setting::set($key, $req->value);
            return response()->json(['message' => 'Setting updated.']);
        });

        // Analytics
        Route::get('/analytics/market', fn() => response()->json(\App\Models\MarketAnalytic::orderByDesc('period_date')->limit(30)->get()));
        Route::get('/analytics/ai-usage', function () {
            $month = \App\Models\AiUsageLog::where('created_at', '>=', now()->startOfMonth());
            return response()->json([
                'logs' => \App\Models\AiUsageLog::with('model:id,name,provider')->orderByDesc('created_at')->limit(100)->get(),
                'stats' => [
                    'total_calls' => (clone $month)->count(),
                    'total_tokens' => (int) (clone $month)->sum('total_tokens'),
                    'cost' => round((float) (clone $month)->sum('cost'), 2),
                    'success_rate' => (clone $month)->count() > 0
                        ? round((clone $month)->where('success', true)->count() / (clone $month)->count() * 100, 1)
                        : 100.0,
                    'avg_latency_ms' => (int) round((float) (clone $month)->avg('latency_ms')),
                ],
            ]);
        });


        // Listing CRUD (create/edit/update)
        Route::get('/listings/{id}', function (string $id) {
            return response()->json(\App\Models\Listing::with(['user:id,name,email,role', 'listable'])->findOrFail($id));
        });
        Route::post('/listings', function (Request $req) {
            $v = $req->validate([
                'user_id' => 'required|uuid|exists:users,id',
                'type' => 'required|in:vehicle,property,rental',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'currency' => 'sometimes|string|size:3',
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:3',
                'status' => 'sometimes|in:pending,active,rejected,sold,expired',
                'brand_id' => 'required_if:type,vehicle',
                'model_id' => 'required_if:type,vehicle',
                'year' => 'required_if:type,vehicle|integer',
                'property_type_id' => 'required_if:type,property',
            ]);
            return \Illuminate\Support\Facades\DB::transaction(function () use ($v) {
                $listable = match ($v['type']) {
                    'vehicle' => \App\Models\Vehicle::create([
                        'brand_id' => $v['brand_id'], 'model_id' => $v['model_id'], 'year' => $v['year'],
                        'condition' => $v['condition'] ?? 'used',
                    ]),
                    'property' => \App\Models\Property::create([
                        'property_type_id' => $v['property_type_id'],
                    ]),
                    'rental' => \App\Models\Rental::create(['title' => $v['title']]),
                };
                $listing = \App\Models\Listing::create([
                    'user_id' => $v['user_id'], 'type' => $v['type'],
                    'listable_type' => get_class($listable), 'listable_id' => $listable->id,
                    'title' => $v['title'], 'description' => $v['description'] ?? null,
                    'price' => $v['price'], 'currency' => $v['currency'] ?? 'GBP',
                    'city' => $v['city'] ?? null, 'country' => $v['country'] ?? null,
                    'status' => $v['status'] ?? 'active',
                    'published_at' => ($v['status'] ?? 'active') === 'active' ? now() : null,
                ]);
                return response()->json(['listing' => $listing->fresh(['listable', 'user:id,name,email'])], 201);
            });
        });
        Route::put('/listings/{id}', function (Request $req, string $id) {
            $listing = \App\Models\Listing::with('listable')->findOrFail($id);
            $listing->update($req->only(['title', 'description', 'price', 'currency', 'city', 'district', 'status', 'is_featured', 'is_premium', 'price_negotiable']));

            if ($listing->listable) {
                if ($listing->type === 'vehicle') {
                    $listing->listable->update($req->only(['year', 'mileage', 'fuel_type', 'transmission', 'body_type', 'color', 'condition', 'engine_size']));
                } elseif ($listing->type === 'property') {
                    $listing->listable->update($req->only(['bedrooms', 'bathrooms', 'area_sqm', 'floor_number', 'build_year', 'furnishing', 'has_parking', 'has_garden', 'has_pool', 'has_elevator']));
                }
            }
            return response()->json($listing->fresh(['listable', 'user:id,name,email']));
        });
        // Admin image management — no ownership check, admin can edit any listing's gallery.
        Route::post('/listings/{id}/upload-images', function (Request $req, string $id) {
            $listing = \App\Models\Listing::findOrFail($id);
            $req->validate(['images' => 'required|array|min:1', 'images.*' => 'image|max:10240']);
            $urls = [];
            foreach ($req->file('images') as $file) {
                $path = $file->store('listings/'.$id, 'public');
                $urls[] = '/storage/'.$path;
            }
            $existing = $listing->images ?? [];
            if (is_string($existing)) $existing = json_decode($existing, true) ?? [];
            $all = array_merge($existing, $urls);
            $listing->update(['images' => $all, 'thumbnail' => $listing->thumbnail ?: ($all[0] ?? null)]);
            return response()->json(['images' => $all, 'message' => count($urls).' images uploaded.']);
        });
        Route::delete('/listings/{id}/images', function (Request $req, string $id) {
            $listing = \App\Models\Listing::findOrFail($id);
            $req->validate(['url' => 'required|string']);
            $existing = $listing->images ?? [];
            if (is_string($existing)) $existing = json_decode($existing, true) ?? [];
            $remaining = array_values(array_filter($existing, fn($u) => $u !== $req->url));
            $listing->update(['images' => $remaining, 'thumbnail' => $listing->thumbnail === $req->url ? ($remaining[0] ?? null) : $listing->thumbnail]);
            return response()->json(['images' => $remaining]);
        });
        Route::put('/listings/{id}/thumbnail', function (Request $req, string $id) {
            $listing = \App\Models\Listing::findOrFail($id);
            $req->validate(['url' => 'required|string']);
            $listing->update(['thumbnail' => $req->url]);
            return response()->json(['thumbnail' => $listing->thumbnail]);
        });

        // Subscription Plans CRUD
        Route::get('/subscription-plans', fn() => response()->json(\App\Models\SubscriptionPlan::orderBy('sort_order')->get()));
        Route::post('/subscription-plans', function (Request $req) {
            $v = $req->validate(['name'=>'required|string', 'price_monthly'=>'required|numeric', 'price_yearly'=>'nullable|numeric', 'max_listings'=>'required|integer', 'description'=>'nullable|string', 'is_active'=>'boolean']);
            $plan = \App\Models\SubscriptionPlan::create($v + ['currency'=>'GBP', 'slug'=>\Str::slug($req->name)]);
            return response()->json($plan, 201);
        });
        Route::put('/subscription-plans/{id}', function (Request $req, string $id) {
            $plan = \App\Models\SubscriptionPlan::findOrFail($id);
            $plan->update($req->only(['name','price_monthly','price_yearly','max_listings','description','is_active','sort_order','max_photos_per_listing','ai_price_analysis','featured_listing','priority_support']));
            return response()->json($plan);
        });
        Route::delete('/subscription-plans/{id}', function (string $id) {
            \App\Models\SubscriptionPlan::findOrFail($id)->delete();
            return response()->json(['message' => 'Plan deleted.']);
        });

        // Subscriptions management
        Route::get('/subscriptions', function (Request $req) {
            $q = \App\Models\Subscription::with(['user:id,name,email,role', 'plan:id,name,price_monthly,currency'])
                ->when($req->status, fn($q) => $q->where('status', $req->status))
                ->when($req->search, fn($q) => $q->whereHas('user', fn($u) => $u->where('name','like','%'.$req->search.'%')->orWhere('email','like','%'.$req->search.'%')))
                ->orderByDesc('created_at');
            return response()->json($q->paginate(20));
        });
        Route::post('/subscriptions', function (Request $req) {
            $v = $req->validate(['user_id'=>'required|uuid', 'plan_id'=>'required|uuid', 'billing_cycle'=>'in:monthly,yearly', 'status'=>'in:active,trial,cancelled,expired']);
            $plan = \App\Models\SubscriptionPlan::findOrFail($v['plan_id']);
            $sub = \App\Models\Subscription::create([
                'user_id' => $v['user_id'],
                'plan_id' => $v['plan_id'],
                'status' => $v['status'] ?? 'active',
                'billing_cycle' => $v['billing_cycle'] ?? 'monthly',
                'amount' => $plan->price_monthly,
                'currency' => 'GBP',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
            return response()->json($sub->load(['user:id,name,email', 'plan:id,name,price_monthly']), 201);
        });
        Route::put('/subscriptions/{id}', function (Request $req, string $id) {
            $sub = \App\Models\Subscription::findOrFail($id);
            $sub->update($req->only(['status','plan_id','billing_cycle','current_period_end']));
            return response()->json($sub->fresh(['user:id,name,email', 'plan:id,name,price_monthly']));
        });
        Route::delete('/subscriptions/{id}', function (string $id) {
            $sub = \App\Models\Subscription::findOrFail($id);
            $sub->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            return response()->json(['message' => 'Subscription cancelled.']);
        });
        Route::get('/subscriptions/stats', function () {
            return response()->json([
                'total_active' => \App\Models\Subscription::where('status','active')->count(),
                'total_revenue' => \App\Models\Subscription::where('status','active')->sum('amount'),
                'by_plan' => \App\Models\Subscription::where('status','active')->with('plan:id,name')->selectRaw('plan_id, count(*) as count')->groupBy('plan_id')->get(),
                'new_this_month' => \App\Models\Subscription::where('created_at','>=',now()->startOfMonth())->count(),
                'cancelled_this_month' => \App\Models\Subscription::whereNotNull('cancelled_at')->where('cancelled_at','>=',now()->startOfMonth())->count(),
            ]);
        });

        // Agency / Dealer management
        Route::get('/agencies', function (Request $req) {
            $q = \App\Models\User::with(['profile', 'activeSubscription.plan'])
                ->whereIn('role', ['agency','dealer'])
                ->when($req->role, fn($q) => $q->where('role', $req->role))
                ->when($req->search, fn($q) => $q->where(fn($u) => $u->where('name','like','%'.$req->search.'%')->orWhere('email','like','%'.$req->search.'%')))
                ->withCount('listings')
                ->orderByDesc('created_at');
            return response()->json($q->paginate(20));
        });
        Route::put('/agencies/{id}', [UserManagementController::class, 'updateAgency']);
        Route::post('/agencies/{id}/verify', [UserManagementController::class, 'verifyAgency']);

        // Advertisements management
        Route::get('/advertisements', fn() => response()->json(\App\Models\Advertisement::orderByDesc('created_at')->get()));
        Route::post('/advertisements', function (Request $req) {
            $v = $req->validate(['name'=>'required|string','slot'=>'required|in:top10,sidebar,banner,homepage','type'=>'required|in:vehicle,property,both','content'=>'nullable|string','link'=>'nullable|url','starts_at'=>'nullable|date','ends_at'=>'nullable|date','listing_id'=>'nullable|uuid']);
            $ad = \App\Models\Advertisement::create($v + ['is_active'=>true]);
            return response()->json($ad, 201);
        });
        Route::put('/advertisements/{id}', function (Request $req, string $id) {
            $ad = \App\Models\Advertisement::findOrFail($id);
            $ad->update($req->only(['name','slot','type','content','link','is_active','starts_at','ends_at','listing_id']));
            return response()->json($ad);
        });
        Route::delete('/advertisements/{id}', function (string $id) {
            \App\Models\Advertisement::findOrFail($id)->delete();
            return response()->json(['message'=>'Deleted.']);
        });

        // Finance
        Route::get('/financial/overview', [\App\Http\Controllers\Admin\FinanceController::class, 'overview']);
        Route::get('/financial/payments', [\App\Http\Controllers\Admin\FinanceController::class, 'payments']);

        // Market analytics & live activity
        Route::get('/market/overview', [\App\Http\Controllers\Admin\MarketAnalyticsController::class, 'overview']);
        Route::get('/live/snapshot', [\App\Http\Controllers\Admin\LiveAnalyticsController::class, 'snapshot']);

        // Fraud
        Route::get('/fraud/overview', [\App\Http\Controllers\Admin\FraudController::class, 'overview']);
        Route::get('/fraud/reports', [\App\Http\Controllers\Admin\FraudController::class, 'reports']);
        Route::put('/fraud/reports/{id}', [\App\Http\Controllers\Admin\FraudController::class, 'update']);

        // Support tickets
        Route::get('/support/tickets', [\App\Http\Controllers\Admin\SupportController::class, 'index']);
        Route::get('/support/agents', [\App\Http\Controllers\Admin\SupportController::class, 'agents']);
        Route::get('/support/tickets/{id}', [\App\Http\Controllers\Admin\SupportController::class, 'show']);
        Route::put('/support/tickets/{id}', [\App\Http\Controllers\Admin\SupportController::class, 'update']);
        Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\Admin\SupportController::class, 'reply']);

        // CSV exports
        Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index']);
        Route::get('/reports/{report}/export', [\App\Http\Controllers\Admin\ReportController::class, 'export']);

        // Content: blog + FAQ
        Route::get('/content/posts', [\App\Http\Controllers\Admin\ContentController::class, 'posts']);
        Route::post('/content/posts', [\App\Http\Controllers\Admin\ContentController::class, 'storePost']);
        Route::get('/content/posts/{id}', [\App\Http\Controllers\Admin\ContentController::class, 'showPost']);
        Route::put('/content/posts/{id}', [\App\Http\Controllers\Admin\ContentController::class, 'updatePost']);
        Route::delete('/content/posts/{id}', [\App\Http\Controllers\Admin\ContentController::class, 'destroyPost']);
        Route::get('/content/categories', [\App\Http\Controllers\Admin\ContentController::class, 'categories']);
        Route::post('/content/categories', [\App\Http\Controllers\Admin\ContentController::class, 'storeCategory']);
        Route::delete('/content/categories/{id}', [\App\Http\Controllers\Admin\ContentController::class, 'destroyCategory']);
        Route::get('/content/faqs', [\App\Http\Controllers\Admin\ContentController::class, 'faqs']);
        Route::post('/content/faqs', [\App\Http\Controllers\Admin\ContentController::class, 'storeFaq']);
        Route::put('/content/faqs/{id}', [\App\Http\Controllers\Admin\ContentController::class, 'updateFaq']);
        Route::delete('/content/faqs/{id}', [\App\Http\Controllers\Admin\ContentController::class, 'destroyFaq']);

        // Announcements (the admin "Notifications" page)
        Route::get('/announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'index']);
        Route::get('/announcements/audience-size', [\App\Http\Controllers\Admin\AnnouncementController::class, 'audienceSize']);
        Route::post('/announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'store']);

        // Homepage sections management
        Route::get('/homepage-sections', fn() => response()->json(\App\Models\HomepageSection::orderBy('sort_order')->get()));
        Route::put('/homepage-sections/{id}', function (Request $req, string $id) {
            $sec = \App\Models\HomepageSection::findOrFail($id);
            $sec->update($req->only(['title','subtitle','is_active','sort_order','config']));
            return response()->json($sec);
        });
        Route::post('/homepage-sections/reorder', function (Request $req) {
            foreach ($req->order ?? [] as $i => $id) {
                \App\Models\HomepageSection::where('id', $id)->update(['sort_order' => $i + 1]);
            }
            return response()->json(['message' => 'Reordered.']);
        });

        // Featured/Best listings for homepage
        Route::get('/featured-listings', function (Request $req) {
            $q = \App\Models\Listing::with(['user:id,name', 'listable'])
                ->where('status','active')
                ->when($req->type, fn($q) => $q->where('type', $req->type))
                ->orderByDesc('is_featured')->orderByDesc('ai_trust_score');
            return response()->json($q->paginate(20));
        });
        Route::post('/listings/{id}/toggle-premium', function (string $id) {
            $l = \App\Models\Listing::findOrFail($id);
            $l->update(['is_premium' => !$l->is_premium]);
            return response()->json(['is_premium' => $l->is_premium]);
        });
    });
});
