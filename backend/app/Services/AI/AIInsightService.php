<?php

namespace App\Services\AI;

use App\Models\Listing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates the short human-readable insight sentence shown next to a
 * listing's scores. This is the ONLY place OpenAI is allowed to influence
 * output — it never produces the numeric scores, only prose that
 * summarizes numbers already computed deterministically.
 */
class AIInsightService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', '');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
    }

    public function summarize(Listing $listing, array $price, array $trust, array $demand, array $investment): string
    {
        if (empty($this->apiKey)) {
            return $this->fallbackSummary($price, $trust, $demand);
        }

        $prompt = $this->buildPrompt($listing, $price, $trust, $demand, $investment);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(12)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.4,
            'max_tokens' => 80,
        ]);

        if (!$response->successful()) {
            Log::warning('AIInsightService: OpenAI call failed with status ' . $response->status());
            return $this->fallbackSummary($price, $trust, $demand);
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));

        return $text ?: $this->fallbackSummary($price, $trust, $demand);
    }

    private function buildPrompt(Listing $listing, array $price, array $trust, array $demand, array $investment): string
    {
        return <<<PROMPT
Write ONE plain sentence (max 25 words, no markdown) summarizing this listing's market position for a buyer. Be factual, no exclamation marks.

Listing: {$listing->title} — {$listing->currency} {$listing->price}
Price battery: {$price['battery_score']}/100 ({$price['price_status']})
Market average: {$listing->currency} {$price['market_avg']}
Trust score: {$trust['score']}/100
Demand score: {$demand['score']}/100
Investment score: {$investment['score']}/100
PROMPT;
    }

    private function fallbackSummary(array $price, array $trust, array $demand): string
    {
        $parts = [];
        $parts[] = match ($price['price_status']) {
            'fair' => 'Priced fairly against comparable listings',
            'slightly_overpriced' => 'Priced slightly above comparable listings',
            default => 'Priced above comparable listings',
        };
        if ($trust['score'] >= 75) {
            $parts[] = 'strong seller trust';
        }
        if ($demand['score'] >= 65) {
            $parts[] = 'above-average buyer interest';
        }

        return implode(', with ', array_filter($parts)) . '.';
    }
}
