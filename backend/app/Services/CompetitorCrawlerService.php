<?php

namespace App\Services;

use App\Models\CompetitorSource;
use App\Models\CompetitorListing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorCrawlerService
{
    // Crawl a single source and store results
    public function crawlSource(CompetitorSource $source): array
    {
        if (!$source->is_active) {
            return ['status' => 'skipped', 'reason' => 'Source is disabled'];
        }

        $results = ['imported' => 0, 'updated' => 0, 'errors' => 0, 'source' => $source->name];

        try {
            $listings = match (true) {
                str_contains($source->url, 'kktcarabam.com') => $this->crawlKktCarabam($source),
                str_contains($source->url, 'noyanlarinvest.com') => $this->crawlNoyanlarinvest($source),
                default => $this->crawlGeneric($source),
            };

            foreach ($listings as $item) {
                $existing = CompetitorListing::where('source_id', $source->id)
                    ->where('external_id', $item['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update($item);
                    $results['updated']++;
                } else {
                    CompetitorListing::create(array_merge($item, ['source_id' => $source->id]));
                    $results['imported']++;
                }
            }

            $source->update(['last_scraped_at' => now()]);

        } catch (\Throwable $e) {
            Log::error('Competitor crawl failed', ['source' => $source->name, 'error' => $e->getMessage()]);
            $results['errors']++;
            $results['error_message'] = $e->getMessage();
        }

        return $results;
    }

    // Crawl all active sources
    public function crawlAll(): array
    {
        $sources = CompetitorSource::where('is_active', true)->get();
        $summary = [];
        foreach ($sources as $source) {
            $summary[] = $this->crawlSource($source);
        }
        return $summary;
    }

    private function crawlKktCarabam(CompetitorSource $source): array
    {
        $listings = [];
        try {
            // Fetch the vehicle listings page
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AIPriceBot/1.0)'])
                ->get($source->url . '/araclar');

            if (!$response->ok()) {
                throw new \RuntimeException('KKTCarabam refused the request; no sample listings were imported.');
            }

            $html = $response->body();
            // Parse listing cards - KKT Carabam uses standard listing structure
            preg_match_all('/<div[^>]+class="[^"]*listing[^"]*"[^>]*>(.*?)<\/div>/si', $html, $cards);

            foreach (array_slice($cards[1] ?? [], 0, 50) as $i => $card) {
                $title = $this->extractText($card, '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i');
                $price = $this->extractPrice($card);
                $url = $this->extractHref($card, $source->url);
                $externalId = 'kkt-' . md5($url ?: $title . $i);

                if ($title || $price) {
                    $listings[] = [
                        'external_id' => $externalId,
                        'type' => 'vehicle',
                        'title' => $title ?: 'Vehicle Listing',
                        'price' => $price,
                        'currency' => 'TRY',
                        'country' => 'CY',
                        'city' => $this->extractCity($card, $html),
                        'url' => $url,
                        'is_active' => true,
                        'listed_at' => now(),
                        'attributes' => $this->extractAttributes($card),
                    ];
                }
            }

            // If HTML parsing found nothing, try structured data approach
            if (empty($listings)) {
                throw new \RuntimeException('KKTCarabam returned no parseable listings; use the authorized export importer.');
            }

        } catch (\Throwable $e) {
            Log::warning('KKT Carabam crawl failed: ' . $e->getMessage());
            throw $e;
        }

        return $listings;
    }

    private function crawlNoyanlarinvest(CompetitorSource $source): array
    {
        $listings = [];
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AIPriceBot/1.0)'])
                ->get($source->url);

            if (!$response->ok()) {
                throw new \RuntimeException('Noyanlar Invest refused the request (HTTP '.$response->status().'); nothing was imported.');
            }


            $html = $response->body();
            preg_match_all('/<article[^>]*>(.*?)<\/article>/si', $html, $cards);

            foreach (array_slice($cards[1] ?? [], 0, 50) as $i => $card) {
                $title = $this->extractText($card, '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i');
                $price = $this->extractPrice($card);
                $url = $this->extractHref($card, $source->url);
                $externalId = 'noyan-' . md5($url ?: $title . $i);

                if ($title || $price) {
                    $listings[] = [
                        'external_id' => $externalId,
                        'type' => 'property',
                        'title' => $title ?: 'Property Listing',
                        'price' => $price,
                        'currency' => 'EUR',
                        'country' => 'CY',
                        'city' => $this->extractCity($card, $html) ?: 'Lefkosa',
                        'url' => $url,
                        'is_active' => true,
                        'listed_at' => now(),
                        'attributes' => $this->extractAttributes($card),
                    ];
                }
            }

            if (empty($listings)) {
                throw new \RuntimeException('Noyanlar Invest returned no parseable listings; nothing was imported.');
            }

        } catch (\Throwable $e) {
            Log::warning('Noyanlarinvest crawl failed: ' . $e->getMessage());
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('Noyanlar Invest crawl failed: '.$e->getMessage(), 0, $e);
        }

        return $listings;
    }

    private function crawlGeneric(CompetitorSource $source): array
    {
        throw new \RuntimeException('No parser exists for '.$source->url.'. Add a site-specific parser or use the authorized import command; no data was invented.');
    }

    // Extract text content matching a regex pattern
    private function extractText(string $html, string $pattern): ?string
    {
        if (preg_match($pattern, $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        // Fallback: any heading or strong text
        if (preg_match('/<(?:h[1-6]|strong|b)[^>]*>([^<]{5,100})<\/(?:h[1-6]|strong|b)>/i', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return null;
    }

    private function extractPrice(string $html): ?float
    {
        // Match patterns like £12,000 or 12.000 TL or $50,000
        if (preg_match('/(?:£|€|\$|TL|TRY|EUR|GBP|USD)?\s*([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{1,2})?)\s*(?:£|€|\$|TL|TRY|EUR|GBP|USD)?/i', $html, $m)) {
            $raw = preg_replace('/[^0-9.]/', '', str_replace(',', '.', preg_replace('/\.(?=\d{3})/', '', $m[1])));
            $val = (float) $raw;
            return $val > 100 ? $val : null;
        }
        return null;
    }

    private function extractHref(string $html, string $baseUrl): ?string
    {
        if (preg_match('/href="([^"]+)"/i', $html, $m)) {
            $href = $m[1];
            if (str_starts_with($href, 'http')) return $href;
            return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        }
        return null;
    }

    private function extractCity(string $card, string $fullHtml): ?string
    {
        $cities = ['Lefkosa', 'Gazimağusa', 'Girne', 'Guzelyurt', 'Iskele', 'Nicosia', 'Kyrenia', 'Famagusta'];
        foreach ($cities as $city) {
            if (stripos($card . $fullHtml, $city) !== false) return $city;
        }
        return null;
    }

    private function extractAttributes(string $html): array
    {
        $attrs = [];
        // Try to pick up year, mileage, area, bedrooms etc.
        if (preg_match('/(\d{4})\s*(?:model|yıl|year)?/i', $html, $m) && (int)$m[1] >= 1990 && (int)$m[1] <= 2030) {
            $attrs['year'] = (int)$m[1];
        }
        if (preg_match('/([\d,]+)\s*(?:km|miles?)/i', $html, $m)) {
            $attrs['mileage'] = (int) str_replace(',', '', $m[1]);
        }
        if (preg_match('/(\d+)\s*(?:bed|bedroom|yatak)/i', $html, $m)) {
            $attrs['bedrooms'] = (int)$m[1];
        }
        if (preg_match('/([\d,]+)\s*(?:m²|sqm|sqft|sq\.ft)/i', $html, $m)) {
            $attrs['area'] = (int) str_replace(',', '', $m[1]);
        }
        return $attrs;
    }
}
