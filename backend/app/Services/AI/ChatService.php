<?php

namespace App\Services\AI;

use App\Models\Listing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backend for the AI assistant widget. Every reply is grounded in an
 * actual database query — the LLM (when configured) is only allowed to
 * phrase the sentence around real results, never invent facts or numbers.
 */
class ChatService
{
    private string $apiKey;
    private string $model;

    public function __construct(
        private IntentExtractionService $intentService,
        private MatchScoreService $matchService,
    ) {
        $this->apiKey = config('services.openai.key', '');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
    }

    public function respond(string $message): array
    {
        $intent = $this->intentService->extract($message);

        if (!$intent['asset_type']) {
            return $this->clarifyingResponse();
        }

        $listings = $this->search($intent);

        if ($listings->isEmpty()) {
            return [
                'message' => "I couldn't find any active {$intent['asset_type']} listings matching that yet. Try a different budget or location.",
                'intent' => $intent,
                'results' => [],
                'suggestions' => ['Show all ' . $intent['asset_type'] . 's', 'Increase budget', 'Any location'],
            ];
        }

        $results = $this->scoreResults($listings, $intent);
        $message = $this->buildReply($intent, $results);

        return [
            'message' => \App\Support\BrandGuard::scrub($message),
            'intent' => $intent,
            'results' => $results,
            'suggestions' => ['Cheaper', 'Higher trust', 'Show more'],
        ];
    }

    private function search(array $intent)
    {
        return Listing::where('type', $intent['asset_type'])
            ->where('status', 'active')
            ->when($intent['budget_max'], fn ($q) => $q->where('price', '<=', $intent['budget_max']))
            ->when($intent['city'], fn ($q) => $q->where('city', $intent['city']))
            ->orderByRaw('ai_deal_score IS NULL, ai_deal_score DESC')
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'slug', 'title', 'type', 'price', 'currency', 'city', 'ai_deal_score', 'ai_price_score', 'ai_trust_score', 'ai_price_status', 'market_avg_price', 'ai_score_breakdown']);
    }

    private function scoreResults($listings, array $intent): array
    {
        $preferences = [
            'budget_max' => $intent['budget_max'],
            'city' => $intent['city'],
            'priorities' => ['price' => 60, 'trust' => 40],
        ];

        return $listings->map(function (Listing $listing) use ($preferences) {
            $scores = [
                'price' => (float) ($listing->ai_price_score ?? 50),
                'trust' => (float) ($listing->ai_trust_score ?? 50),
                'demand' => 50.0,
                'investment' => 50.0,
            ];
            $match = $this->matchService->score($listing, $preferences, $scores);

            return [
                'id' => $listing->id,
                'slug' => $listing->slug,
                'title' => $listing->title,
                'price' => $listing->price,
                'currency' => $listing->currency,
                'city' => $listing->city,
                'deal_score' => $listing->ai_deal_score,
                'price_status' => $listing->ai_price_status,
                'match_score' => $match['score'],
                'match_reasons' => $match['reasons'],
            ];
        })->values()->all();
    }

    private function buildReply(array $intent, array $results): string
    {
        $count = count($results);
        $top = $results[0];

        $fallback = sprintf(
            'I found %d strong match%s. Top pick: %s at %s %s%s.',
            $count,
            $count === 1 ? '' : 'es',
            $top['title'],
            number_format($top['price']),
            $top['currency'],
            $top['match_score'] ? " ({$top['match_score']}% match)" : ''
        );

        if (empty($this->apiKey)) {
            return $fallback;
        }

        try {
            $prompt = $this->buildPrompt($intent, $results, $count);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
                'max_tokens' => 90,
            ]);

            if ($response->successful()) {
                $text = trim((string) $response->json('choices.0.message.content', ''));
                if ($text) {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ChatService: OpenAI reply generation failed: ' . $e->getMessage());
        }

        return $fallback;
    }

    private function buildPrompt(array $intent, array $results, int $count): string
    {
        $lines = array_map(
            fn ($r) => "- {$r['title']}: {$r['currency']} " . number_format($r['price']) . ", match {$r['match_score']}%, deal score " . ($r['deal_score'] ?? 'n/a'),
            array_slice($results, 0, 5)
        );
        $listText = \App\Support\BrandGuard::scrub(implode("\n", $lines));
        $rules = \App\Support\BrandGuard::PROMPT_RULES;

        return <<<PROMPT
You are a marketplace search assistant. Write ONE short reply (max 30 words, no markdown) to a buyer searching for a {$intent['asset_type']}. State the count found and name the single best match by title. Use ONLY the data below — never invent numbers.

Results found: {$count}
{$listText}

{$rules}
PROMPT;
    }

    private function clarifyingResponse(): array
    {
        return [
            'message' => 'What are you looking for — a car, a property, or a rental?',
            'intent' => null,
            'results' => [],
            'suggestions' => ['Car', 'Property', 'Rental'],
        ];
    }
}
