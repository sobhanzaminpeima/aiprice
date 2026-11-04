<?php

namespace App\Services\AI;

use App\Support\BrandGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fallback used only when AIPrice.app has too few comparable listings for a
 * statistical estimate. An OpenAI model is asked for a typical asking-price
 * range for the described car or property in North Cyprus. The answer is
 * sanity-checked and always labelled as a low-confidence AI estimate.
 * Returns null when no API key is configured or the answer is not usable.
 */
class AiMarketEstimateService
{
    public function estimate(string $type, string $currency, ?string $city, array $facts, float $askingPrice): ?array
    {
        $key = (string) config('services.openai.key', '');
        if ($key === '' || $askingPrice <= 0) {
            return null;
        }

        $facts = array_filter($facts, fn ($v) => $v !== null && $v !== '');
        $cacheKey = 'ai-est:'.md5(json_encode([$type, $currency, $city, $facts]));

        try {
            $range = Cache::remember($cacheKey, now()->addDays(3), fn () => $this->ask($key, $type, $currency, $city, $facts));
        } catch (Throwable $e) {
            Log::info('AI market estimate skipped: '.$e->getMessage());

            return null;
        }

        if (! is_array($range)) {
            return null;
        }
        [$low, $typical, $high] = [(float) ($range['low'] ?? 0), (float) ($range['typical'] ?? 0), (float) ($range['high'] ?? 0)];

        // Reject answers that are malformed or wildly off the asking price.
        if ($low <= 0 || $typical < $low || $high < $typical || $typical < $askingPrice / 5 || $typical > $askingPrice * 5) {
            return null;
        }

        return ['low' => round($low), 'typical' => round($typical), 'high' => round($high),
            'note' => BrandGuard::scrub(mb_substr((string) ($range['note'] ?? ''), 0, 200))];
    }

    private function ask(string $key, string $type, string $currency, ?string $city, array $facts): ?array
    {
        $what = $type === 'vehicle' ? 'used car' : ($type === 'rental' ? 'rental property (monthly rent)' : 'property for sale');
        $lines = collect($facts)->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n");
        $rules = BrandGuard::PROMPT_RULES;
        $prompt = <<<PROMPT
You estimate typical ASKING prices in North Cyprus (TRNC). Item: {$what} in {$city}.
{$lines}
Reply with JSON only: {"low": number, "typical": number, "high": number, "note": "max 20 words"}
All numbers in {$currency}. "typical" is a typical asking price for this item today; low/high bound the usual range.
If you cannot judge, reply {"low":0,"typical":0,"high":0,"note":"unknown"}.
{$rules}
PROMPT;

        $res = Http::withToken($key)->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.2,
            'max_tokens' => 120,
            'response_format' => ['type' => 'json_object'],
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('OpenAI status '.$res->status());
        }
        $json = json_decode((string) $res->json('choices.0.message.content', ''), true);

        return is_array($json) ? $json : null;
    }
}
