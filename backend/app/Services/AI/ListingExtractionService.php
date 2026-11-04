<?php

namespace App\Services\AI;

use App\Models\PropertyType;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a seller's free-text description ("5 bedroom villa in Kyrenia,
 * sea view, 250k GBP, built 2020...") into the structured fields the
 * listing form needs. The LLM only extracts what's explicitly stated —
 * it must leave a field null rather than guess, so the seller always
 * reviews and confirms before anything is saved.
 */
class ListingExtractionService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.nvidia.key', '');
        $this->model = config('services.nvidia.model', 'meta/llama-3.2-11b-vision-instruct');
        $this->baseUrl = config('services.nvidia.base_url', 'https://integrate.api.nvidia.com/v1');
    }

    public function extract(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('AI extraction is not configured.');
        }

        $brands = VehicleBrand::where('is_active', true)->pluck('name')->all();
        $propertyTypes = PropertyType::where('is_active', true)->pluck('name')->all();

        // NVIDIA's free-tier response time for this model is genuinely
        // inconsistent — verified this is NOT about prompt content, size, or
        // encoding (identical English and Persian requests both hang some of
        // the time and both succeed in under a few seconds other times). The
        // hosting gateway's own read timeout is ~55s, so more shorter
        // attempts beats fewer long ones at riding out a bad one.
        set_time_limit(50);

        $data = null;
        $attempts = 0;
        while ($attempts < 3 && $data === null) {
            $attempts++;
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(15)->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                    'model' => $this->model,
                    'temperature' => 0,
                    'max_tokens' => 1024,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt($brands, $propertyTypes)],
                        ['role' => 'user', 'content' => $text],
                    ],
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::warning('ListingExtractionService: connection failed, attempt ' . $attempts . ': ' . $e->getMessage());
                continue;
            }

            if (!$response->successful()) {
                Log::warning('ListingExtractionService: AI call failed with status ' . $response->status() . ' body: ' . $response->body());
                continue;
            }

            $raw = trim((string) $response->json('choices.0.message.content', ''));
            $parsed = json_decode($this->extractJson($raw), true);

            if (!is_array($parsed) || empty($parsed['type'])) {
                continue;
            }

            $data = $parsed;
        }

        if ($data === null) {
            throw new \RuntimeException('AI extraction failed. Please try again or fill the form manually.');
        }

        // The hosted model has a reproducible bug where it sometimes replaces
        // Persian/Farsi script it's asked to echo back with literal "?" runs —
        // happens identically regardless of temperature, so retrying the same
        // prompt never helps. Since the rules already say description/title
        // should stay close to the seller's own words, just use their raw text
        // directly instead of trusting the model's (possibly mangled) copy.
        if ($this->isGarbled($data['description'] ?? null, $text)) {
            $data['description'] = $text;
        }
        if ($this->isGarbled($data['title'] ?? null, $text)) {
            $data['title'] = mb_substr(trim($text), 0, 80);
        }

        return $this->normalize($data, $brands, $propertyTypes);
    }

    /** True when the source text has real (non-ASCII) script but this field replaced it with "?" runs. */
    private function isGarbled(?string $field, string $sourceText): bool
    {
        if (!preg_match('/[^\x00-\x7F]/', $sourceText) || empty($field)) {
            return false;
        }
        return (bool) preg_match('/\?{2,}/', $field);
    }

    /** Models without native JSON mode sometimes wrap the object in prose or ```json fences — pull the {...} out. */
    private function extractJson(string $raw): string
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            return $m[0];
        }
        return $raw;
    }

    private function systemPrompt(array $brands, array $propertyTypes): string
    {
        $brandList = implode(', ', $brands);
        $typeList = implode(', ', $propertyTypes);

        $rules = \App\Support\BrandGuard::PROMPT_RULES;

        return <<<PROMPT
You extract structured listing data from a seller's free-text description of a car or a property for sale in North Cyprus. The text may be in English or Persian/Farsi.

Return ONLY a JSON object, no prose, matching this exact shape:

{
  "type": "vehicle" | "property" | null,
  "title": string | null,
  "description": string,
  "price": number | null,
  "currency": "GBP" | "USD" | "EUR" | "TRY" | null,
  "city": string | null,
  "district": string | null,
  "address": string | null,

  "brand": string | null,
  "model": string | null,
  "year": number | null,
  "mileage": number | null,
  "fuel_type": "petrol" | "diesel" | "electric" | "hybrid" | null,
  "transmission": "manual" | "automatic" | null,
  "body_type": string | null,
  "color": string | null,
  "condition": "new" | "used" | null,

  "property_type": string | null,
  "bedrooms": number | null,
  "bathrooms": number | null,
  "area_sqm": number | null,
  "floor_number": number | null,
  "build_year": number | null,
  "furnishing": "furnished" | "unfurnished" | "semi-furnished" | null,
  "has_parking": boolean | null,
  "has_garden": boolean | null,
  "has_pool": boolean | null,

  "missing_required": string[]
}

Rules:
- Never invent a value that is not stated or clearly implied in the text. If unsure, use null.
- "type" must be "vehicle" for cars/motorcycles, "property" for houses/apartments/villas/land, or null if unclear.
- For vehicles, match "brand" as closely as possible to one of: {$brandList}. If no close match, return the brand as written.
- For properties, match "property_type" as closely as possible to one of: {$typeList}.
- "title" should be a short, natural listing title (max 80 chars) synthesized from the description if the seller didn't give one explicitly.
- "description" should be the seller's text, lightly cleaned up (fix obvious typos, keep their meaning and language), not rewritten from scratch.
- "price" is a plain number with no currency symbol or thousands separator.
- "missing_required" lists which of these are still null and are needed before this can be submitted: for vehicle: type, title, price, brand, model, year. For property: type, title, price, property_type.
- Remove names, URLs and watermarks of other websites from "title" and "description".

{$rules}
PROMPT;
    }

    private function normalize(array $data, array $brands, array $propertyTypes): array
    {
        $data['confidence'] = empty($data['missing_required']) ? 'high' : 'needs_review';
        foreach (['title', 'description'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = \App\Support\BrandGuard::scrub($data[$field]);
            }
        }

        if (!empty($data['brand'])) {
            $data['brand_match'] = $this->closestMatch($data['brand'], $brands);
        }
        if (!empty($data['property_type'])) {
            $data['property_type_match'] = $this->closestMatch($data['property_type'], $propertyTypes);
        }
        if (!empty($data['brand_match']) && !empty($data['model'])) {
            $brand = VehicleBrand::where('name', $data['brand_match'])->first();
            if ($brand) {
                $modelMatch = VehicleModel::where('brand_id', $brand->id)->pluck('name')->all();
                $data['model_match'] = $this->closestMatch($data['model'], $modelMatch);
            }
        }

        return $data;
    }

    /** Simple case-insensitive closest match so "byw" or "بی ام و" style near-misses still map onto a real catalog entry. */
    private function closestMatch(string $needle, array $haystack): ?string
    {
        if (empty($haystack)) {
            return null;
        }
        $needleLower = mb_strtolower(trim($needle));
        foreach ($haystack as $candidate) {
            if (mb_strtolower($candidate) === $needleLower) {
                return $candidate;
            }
        }
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($haystack as $candidate) {
            $distance = levenshtein($needleLower, mb_strtolower($candidate));
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }
        return $bestDistance <= max(3, (int) (mb_strlen($needleLower) * 0.4)) ? $best : null;
    }
}
