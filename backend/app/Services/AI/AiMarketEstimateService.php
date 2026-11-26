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
    /** Why the last estimate() returned null (for the admin diagnostics); null after a success. */
    public static ?string $lastReason = null;

    public function estimate(string $type, string $currency, ?string $city, array $facts, float $askingPrice): ?array
    {
        return $this->estimateWithReason($type, $currency, $city, $facts, $askingPrice)[0];
    }

    /**
     * @return array{0: array{low: float, typical: float, high: float, note: string}|null, 1: string|null} the range, or null and the reason in plain words
     */
    public function estimateWithReason(string $type, string $currency, ?string $city, array $facts, float $askingPrice, bool $useCache = true): array
    {
        self::$lastReason = null;
        $connection = app(ProviderRegistry::class)->resolve();
        $key = $connection['key'];
        if ($key === '') {
            return [null, self::fail('No AI key is saved for the active provider ('.$connection['provider'].'). Add one under Pricing & AI → API Management.')];
        }
        if ($askingPrice <= 0) {
            return [null, self::fail('The listing has no asking price.')];
        }

        $facts = array_filter($facts, fn ($v) => $v !== null && $v !== '');
        $cacheKey = 'ai-est:'.md5(json_encode([$connection['provider'], $connection['model'], $type, $currency, $city, $facts]));

        try {
            $range = $useCache
                ? Cache::remember($cacheKey, now()->addDays(3), fn () => $this->ask($key, $type, $currency, $city, $facts))
                : $this->ask($key, $type, $currency, $city, $facts);
        } catch (Throwable $e) {
            Log::warning('AI market estimate failed: '.$e->getMessage(), ['provider' => $connection['provider'], 'model' => $connection['model']]);

            return [null, self::fail($e->getMessage())];
        }

        if (! is_array($range)) {
            return [null, self::fail('The AI answered, but not in the expected JSON format.')];
        }
        [$low, $typical, $high] = [(float) ($range['low'] ?? 0), (float) ($range['typical'] ?? 0), (float) ($range['high'] ?? 0)];
        if ($low <= 0 || $typical <= 0) {
            return [null, self::fail('The AI said it cannot judge this item ('.mb_substr((string) ($range['note'] ?? 'unknown'), 0, 80).').')];
        }

        // Reject answers that are malformed or wildly off the asking price.
        if ($typical < $low || $high < $typical || $typical < $askingPrice / 5 || $typical > $askingPrice * 5) {
            return [null, self::fail('The AI answer was not credible (typical '.number_format($typical).' against an asking price of '.number_format($askingPrice).') and was discarded.')];
        }

        return [['low' => round($low), 'typical' => round($typical), 'high' => round($high),
            'note' => BrandGuard::scrub(mb_substr((string) ($range['note'] ?? ''), 0, 200))], null];
    }

    private static function fail(string $reason): string
    {
        return self::$lastReason = $reason;
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

        $connection = app(ProviderRegistry::class)->resolve();
        $payload = [
            'model' => $connection['model'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.2,
            // Room for models that think before they answer.
            'max_tokens' => 400,
        ];
        $send = fn (array $body) => Http::withToken($key)->acceptJson()->connectTimeout(8)->timeout(40)->post($connection['base_url'].'/chat/completions', $body);

        $res = $send($payload + ['response_format' => ['type' => 'json_object']]);
        // Not every provider/model supports response_format: ask again plainly.
        if (in_array($res->status(), [400, 422], true)) {
            $res = $send($payload);
        }
        if (! $res->successful()) {
            $detail = mb_substr((string) ($res->json('error.message') ?? $res->json('message') ?? ''), 0, 140);
            throw new \RuntimeException(match ($res->status()) {
                401, 403 => 'The AI provider rejected the key (HTTP '.$res->status().'). Check or replace it in API Management.',
                402 => 'The AI provider reports no credit left (HTTP 402).',
                404 => 'The AI model "'.$connection['model'].'" was not found at the provider (HTTP 404).',
                429 => 'The AI provider rate limit was hit (HTTP 429). Try again in a minute.',
                default => 'The AI provider answered HTTP '.$res->status().($detail !== '' ? ': '.$detail : '').'.',
            });
        }

        $content = trim((string) $res->json('choices.0.message.content', ''));
        // Some models wrap the JSON in prose or code fences.
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }
        $json = json_decode($content, true);

        return is_array($json) ? $json : null;
    }
}
