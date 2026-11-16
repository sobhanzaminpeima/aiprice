<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RecheckSourceListing;
use App\Jobs\RunSourceImport;
use App\Models\AuditLog;
use App\Models\Imports\ImportRun;
use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;
use App\Models\Imports\SourceListingImage;
use App\Services\Imports\ListingImporter;
use App\Services\Imports\Normalizer;
use App\Services\Imports\PublicationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin import dashboard API (/api/v1/admin/imports/*, permission manage_imports).
 * Seller contact fields are returned here only — never by public endpoints.
 */
class SourceImportController extends Controller
{
    public function __construct(private ListingImporter $listings, private PublicationPolicy $policy) {}

    public function overview(): JsonResponse
    {
        ImportSource::ensureKnown();
        $sources = ImportSource::orderBy('name')->get()->map(function (ImportSource $s) {
            $byStatus = SourceListing::where('import_source_id', $s->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

            return $s->toArray() + [
                'stats' => [
                    'by_status' => $byStatus,
                    'candidates' => $byStatus->sum(),
                    'images_stored' => SourceListingImage::whereHas('sourceListing', fn ($q) => $q->where('import_source_id', $s->id))->where('status', 'stored')->count(),
                    'images_public' => SourceListingImage::whereHas('sourceListing', fn ($q) => $q->where('import_source_id', $s->id))->whereNotNull('public_paths')->count(),
                    'contacts_pending' => SourceListing::where('import_source_id', $s->id)->whereNotNull('listing_id')->where('contact_publication_status', ImportSource::PENDING)->count(),
                    'contacts_public' => SourceListing::where('import_source_id', $s->id)->where('status', 'published')->where('contact_publication_status', ImportSource::AUTHORIZED)->count(),
                    'failed' => SourceListing::where('import_source_id', $s->id)->where('status', 'failed')->count(),
                    'inactive_at_source' => SourceListing::where('import_source_id', $s->id)->where('source_active', false)->count(),
                ],
                'latest_run' => $s->runs()->latest()->first(),
            ];
        });

        return response()->json(['sources' => $sources]);
    }

    public function updateSource(Request $request, string $id): JsonResponse
    {
        $source = ImportSource::findOrFail($id);
        $v = $request->validate([
            'status' => 'sometimes|in:active,paused',
            'listing_data_permission' => ['sometimes', Rule::in(ImportSource::PERMISSION_STATES)],
            'image_permission' => ['sometimes', Rule::in(ImportSource::PERMISSION_STATES)],
            'contact_permission' => ['sometimes', Rule::in(ImportSource::PERMISSION_STATES)],
            'attribution_required' => 'sometimes|boolean',
            'permission_reference' => 'nullable|string|max:5000',
            'permission_granted_at' => 'nullable|date',
            'permission_max_listings' => 'nullable|integer|min:1|max:10000',
            'request_delay_ms' => 'sometimes|integer|min:1000|max:60000',
            'notes' => 'nullable|string|max:5000',
        ]);
        $grants = collect(['listing_data_permission', 'image_permission', 'contact_permission'])
            ->filter(fn ($f) => ($v[$f] ?? null) === ImportSource::AUTHORIZED && $source->{$f} !== ImportSource::AUTHORIZED);
        if ($grants->isNotEmpty() && blank($v['permission_reference'] ?? $source->permission_reference)) {
            return response()->json(['message' => 'Record where the permission is documented (permission_reference) before marking anything authorized.'], 422);
        }

        $old = $source->only(array_keys($v));
        $source->update($v + ($grants->isNotEmpty() ? ['permission_recorded_by' => $request->user()->id] : []));
        $this->audit($request, 'import.source_updated', $source, $old, $source->only(array_keys($v)));

        // Revoking a permission immediately withdraws what depended on it.
        $withdrawn = 0;
        if (! $source->listingDataAuthorized() || ! $source->imagesAuthorized()) {
            foreach (SourceListing::with('source')->where('import_source_id', $source->id)->where('status', 'published')->get() as $sl) {
                $this->listings->unpublish($sl, 'Source permission changed', $request->user());
                $withdrawn++;
            }
        }
        if (! $source->contactsAuthorized()) {
            SourceListing::where('import_source_id', $source->id)->where('contact_publication_status', ImportSource::AUTHORIZED)
                ->update(['contact_publication_status' => ImportSource::PENDING]);
        }

        return response()->json(['source' => $source->fresh(), 'withdrawn' => $withdrawn]);
    }

    public function startRun(Request $request, string $id): JsonResponse
    {
        $source = ImportSource::findOrFail($id);
        $v = $request->validate([
            'mode' => 'required|in:dry_run,import',
            'target' => 'sometimes|integer|min:20|max:50',
            'pages' => 'sometimes|integer|min:1|max:10',
            'max_fetch' => 'sometimes|integer|min:10|max:200',
            'from_run' => ['nullable', 'uuid', Rule::exists('import_runs', 'id')->where('import_source_id', $source->id)->where('mode', 'dry_run')],
        ]);
        if ($source->isPaused()) {
            return response()->json(['message' => 'Source is paused. Resume it first.'], 409);
        }
        if (ImportRun::where('import_source_id', $source->id)->whereIn('status', ['queued', 'running'])->exists()) {
            return response()->json(['message' => 'A run is already in progress for this source.'], 409);
        }
        $run = ImportRun::create([
            'import_source_id' => $source->id, 'triggered_by' => $request->user()->id,
            'mode' => $v['mode'], 'status' => 'queued', 'target' => $v['target'] ?? 35,
        ]);
        RunSourceImport::dispatch($run->id, array_filter([
            'pages' => $v['pages'] ?? 4, 'max_fetch' => $v['max_fetch'] ?? 90, 'from_run' => $v['from_run'] ?? null,
        ]));
        $this->audit($request, 'import.run_started', $source, null, ['run' => $run->id, 'mode' => $run->mode]);

        return response()->json(['run' => $run], 202);
    }

    public function runs(Request $request): JsonResponse
    {
        return response()->json(ImportRun::with('triggeredBy:id,name')->latest()
            ->when($request->query('source'), fn ($q, $s) => $q->where('import_source_id', $s))
            ->paginate(20));
    }

    public function run(string $id): JsonResponse
    {
        return response()->json(['run' => ImportRun::with('source', 'triggeredBy:id,name')->findOrFail($id)]);
    }

    public function listings(Request $request): JsonResponse
    {
        $page = SourceListing::with(['source', 'listing:id,slug,status,title,price,currency,ai_price_status,market_avg_price,ai_confidence_score'])
            ->withCount(['images as images_stored' => fn ($q) => $q->where('status', 'stored')])
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('run'), fn ($q, $r) => $q->where('last_run_id', $r))
            ->when($request->query('source'), fn ($q, $s) => $q->where('import_source_id', $s))
            ->orderByDesc('selection_score')
            ->paginate(min(100, (int) $request->query('per_page', 50)));
        $page->getCollection()->transform(fn (SourceListing $sl) => $this->present($sl, false));

        return response()->json($page);
    }

    public function listing(string $id): JsonResponse
    {
        $sl = SourceListing::with(['source', 'listing', 'images'])->findOrFail($id);

        return response()->json(['source_listing' => $this->present($sl, true)]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $sl = SourceListing::with('source')->findOrFail($id);
        try {
            $listing = $this->listings->publish($sl, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'blockers' => $this->policy->blockers($sl)], 422);
        }

        return response()->json(['listing' => $listing->only(['id', 'slug', 'status'])]);
    }

    public function unpublish(Request $request, string $id): JsonResponse
    {
        $reason = $request->validate(['reason' => 'required|string|max:500'])['reason'];
        $sl = SourceListing::with('source')->findOrFail($id);
        $this->listings->unpublish($sl, $reason, $request->user());

        return response()->json(['source_listing' => $this->present($sl->fresh(['source', 'listing']), false)]);
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $reason = $request->validate(['reason' => 'required|string|max:500'])['reason'];
        $sl = SourceListing::with('source')->findOrFail($id);
        $this->listings->remove($sl, $reason, $request->user());

        return response()->json(['source_listing' => $this->present($sl->fresh(['source', 'listing']), false)]);
    }

    public function recheck(string $id): JsonResponse
    {
        $sl = SourceListing::findOrFail($id);
        RecheckSourceListing::dispatch($sl->id);

        return response()->json(['queued' => true], 202);
    }

    public function analyze(string $id): JsonResponse
    {
        return response()->json(['queued' => $this->listings->queueValuation(SourceListing::findOrFail($id))], 202);
    }

    /** Correction or removal of seller contact details. */
    public function updateContact(Request $request, string $id): JsonResponse
    {
        $sl = SourceListing::with('source')->findOrFail($id);
        $v = $request->validate([
            'seller_display_name' => 'nullable|string|max:120',
            'seller_phone' => 'nullable|string|max:40',
            'contact_publication_status' => ['required', Rule::in(ImportSource::PERMISSION_STATES)],
        ]);
        if ($v['contact_publication_status'] === ImportSource::AUTHORIZED && ! $sl->source->contactsAuthorized()) {
            return response()->json(['message' => 'The source has no recorded permission to republish seller contacts.'], 422);
        }
        $e164 = array_key_exists('seller_phone', $v) ? Normalizer::phoneE164($v['seller_phone']) : $sl->seller_phone_e164;
        if (! empty($v['seller_phone']) && ! $e164) {
            return response()->json(['message' => 'Enter a complete phone number including country code.'], 422);
        }
        $old = ['seller_display_name' => (bool) $sl->seller_display_name, 'has_phone' => (bool) $sl->seller_phone_e164, 'contact_publication_status' => $sl->contact_publication_status];
        $sl->update([
            'seller_display_name' => array_key_exists('seller_display_name', $v) ? $v['seller_display_name'] : $sl->seller_display_name,
            'seller_phone' => array_key_exists('seller_phone', $v) ? ($e164 ? $v['seller_phone'] : null) : $sl->seller_phone,
            'seller_phone_e164' => $e164,
            'contact_publication_status' => $v['contact_publication_status'],
        ]);
        if ($sl->listing && ($sl->listing->meta['contact_phone'] ?? null) !== null && ! $this->policy->contactMayBePublic($sl->fresh('source'))) {
            $meta = $sl->listing->meta;
            unset($meta['contact_phone']);
            $sl->listing->update(['meta' => $meta]);
        }
        // Values are not written to the audit log, only what changed.
        $this->audit($request, 'import.contact_updated', $sl, $old, ['seller_display_name' => (bool) $sl->seller_display_name, 'has_phone' => (bool) $e164, 'contact_publication_status' => $sl->contact_publication_status]);

        return response()->json(['source_listing' => $this->present($sl->fresh(['source', 'listing']), true)]);
    }

    /** Private image preview for admins via short-lived signed URLs. */
    public function image(Request $request, string $id): StreamedResponse
    {
        $img = SourceListingImage::findOrFail($id);
        $path = $request->query('v') === 'thumb' ? ($img->thumb_path ?: $img->original_path) : ($img->webp_path ?: $img->original_path);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=600', 'X-Robots-Tag' => 'noindex']);
    }

    private function present(SourceListing $sl, bool $detail): array
    {
        $out = $sl->toArray();
        $out['blockers'] = $this->policy->blockers($sl);
        $out['import_key'] = SourceListing::importKey($sl->source->key, $sl->source_listing_id);
        $out['seller'] = [
            'type' => $sl->seller_type,
            'display_name' => $sl->seller_display_name,
            'has_phone' => (bool) $sl->seller_phone_e164,
            'phone_e164' => $detail ? $sl->seller_phone_e164 : null,
            'contact_publication_status' => $sl->contact_publication_status,
        ];
        unset($out['source']);
        if ($detail) {
            $out['source_description'] = $sl->source_description;
            $out['images'] = $sl->images->map(fn (SourceListingImage $i) => $i->only(['id', 'position', 'source_url', 'sha256', 'width', 'height', 'bytes', 'is_cover', 'status', 'error', 'public_paths']) + [
                'preview_url' => $i->status === 'stored' ? URL::temporarySignedRoute('imports.image', now()->addMinutes(30), ['id' => $i->id]) : null,
                'thumb_url' => $i->status === 'stored' ? URL::temporarySignedRoute('imports.image', now()->addMinutes(30), ['id' => $i->id, 'v' => 'thumb']) : null,
            ])->all();
        }

        return $out;
    }

    private function audit(Request $request, string $action, $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id, 'action' => $action, 'model_type' => $model::class, 'model_id' => $model->id,
            'old_values' => $old, 'new_values' => $new, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
