<?php

namespace App\Services\AI;

use App\Models\Listing;
use App\Services\AiPriceService;
use Illuminate\Support\Facades\Log;

/**
 * Runs the AI price check for listings right now, inside the admin's request,
 * instead of waiting for a queue worker. Used by the admin "Re-run AI price
 * check" button: it needs no cron and tells the admin exactly what happened.
 */
class PriceCheckRunner
{
    /** @return Listing[]|\Illuminate\Support\Collection active listings that still need a price check, newest first */
    public function pending(int $limit = 500)
    {
        return Listing::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ai_scored_at')->orWhereNull('price_battery_percent')->orWhere('ai_price_status', 'insufficient_data'))
            ->orderByDesc('published_at')->limit($limit)->get(['id']);
    }

    /**
     * @return array{processed: int, with_estimate: int, still_without: int, remaining: int, stopped_early: bool, reason: string|null, errors: int}
     */
    public function run(int $limit, bool $all = false, int $budgetSeconds = 40): array
    {
        $started = microtime(true);
        $ids = $all
            ? Listing::where('status', 'active')->orderByDesc('published_at')->limit(2000)->pluck('id')
            : $this->pending(2000)->pluck('id');
        $total = $ids->count();

        $done = $withEstimate = $errors = 0;
        $reason = null;
        foreach ($ids->take($limit) as $id) {
            if (microtime(true) - $started > $budgetSeconds) {
                break;
            }
            $listing = Listing::find($id);
            if (! $listing) {
                continue;
            }
            AiMarketEstimateService::$lastReason = null;
            try {
                app(AiPriceService::class)->analyzeListingPrice($listing);
            } catch (\Throwable $e) {
                $errors++;
                $reason ??= 'A listing could not be analysed: '.mb_substr($e->getMessage(), 0, 160);
                Log::warning('Admin price check failed', ['listing' => $id, 'error' => $e->getMessage()]);
            }
            $done++;
            $listing->refresh();
            if ($listing->price_battery_percent !== null) {
                $withEstimate++;
            } else {
                $reason ??= AiMarketEstimateService::$lastReason;
            }
        }

        return [
            'processed' => $done,
            'with_estimate' => $withEstimate,
            'still_without' => $done - $withEstimate,
            'remaining' => max(0, ($all ? $total : $this->pending(2000)->count()) - ($all ? $done : 0)),
            'stopped_early' => $done < min($limit, $total),
            'reason' => $reason,
            'errors' => $errors,
        ];
    }

    /** One real estimate request with a made-up car, bypassing the cache, so the admin sees why checks fail. */
    public function diagnose(): array
    {
        $connection = app(ProviderRegistry::class)->resolve();
        [$range, $reason] = app(AiMarketEstimateService::class)->estimateWithReason(
            'vehicle', 'GBP', 'Kyrenia', ['brand' => 'Ford', 'model' => 'Fiesta', 'year' => 2006, 'mileage_km' => 142000, 'fuel' => 'petrol'], 2000, useCache: false,
        );

        return [
            'ok' => $range !== null,
            'provider' => $connection['provider'],
            'model' => $connection['model'],
            'key_saved' => $connection['key'] !== '',
            'range' => $range,
            'reason' => $reason,
        ];
    }
}
