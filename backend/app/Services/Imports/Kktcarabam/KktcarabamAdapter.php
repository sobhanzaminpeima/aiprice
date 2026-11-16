<?php

namespace App\Services\Imports\Kktcarabam;

use App\Services\Imports\Contracts\SourceAdapter;
use App\Services\Imports\RobotsPolicy;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Public-page access to kktcarabam.com.
 *
 * - Identifies itself honestly (no browser impersonation).
 * - Checks robots.txt before the first request and stops if disallowed.
 * - Waits `delayMs` between requests (default 2.5 s).
 * - Treats a Cloudflare challenge / 403 / 429 as "blocked" and stops: it
 *   never retries around protections.
 */
class KktcarabamAdapter implements SourceAdapter
{
    public const USER_AGENT = 'Mozilla/5.0 (compatible; AIPriceListingImporter/1.0; +https://aiprice.app)';

    public const DISCOVERY_PATHS = [
        '/kategori/ikinci-el-araclar-otomobil',
        '/kategori/ikinci-el-araclar-arazi-suv-pick-up',
    ];

    private ?RobotsPolicy $robots = null;

    private float $lastRequestAt = 0.0;

    private bool $blocked = false;

    public function __construct(
        private KktcarabamParser $parser,
        private KktcarabamMapper $mapper,
        private int $delayMs = 2500,
        private ?Closure $sleeper = null,
    ) {}

    public function key(): string
    {
        return 'kktcarabam';
    }

    public function withDelay(int $ms): static
    {
        $this->delayMs = max(0, $ms);

        return $this;
    }

    /** Content-Signal values from robots.txt (e.g. search=yes, ai-train=no), for the run report. */
    public function contentSignals(): array
    {
        return $this->robots?->contentSignals ?? [];
    }

    public function idFromUrl(string $url): ?string
    {
        return KktcarabamParser::idFromUrl($url);
    }

    public function canonicalUrl(string $url): ?string
    {
        return KktcarabamParser::canonicalUrl($url);
    }

    public function discover(int $maxPages): array
    {
        $found = [];
        foreach (self::DISCOVERY_PATHS as $path) {
            for ($page = 1; $page <= $maxPages; $page++) {
                $url = 'https://'.KktcarabamParser::HOST.$path.($page > 1 ? '?p='.$page : '');
                $res = $this->get($url);
                if (! $res || ! $res->successful()) {
                    break;
                }
                $html = $res->body();
                $found += $this->parser->parseIndex($html);
                if (! $this->parser->hasNextPage($html, $page)) {
                    break;
                }
            }
        }

        return $found;
    }

    public function fetchListing(string $url): array
    {
        if (! $this->idFromUrl($url)) {
            return ['status' => 'error', 'http_status' => null, 'mapped' => null, 'error' => 'Not a KKTCarabam listing URL'];
        }
        try {
            $res = $this->get($url);
        } catch (RuntimeException $e) {
            return ['status' => 'blocked', 'http_status' => null, 'mapped' => null, 'error' => $e->getMessage()];
        }
        if (! $res) {
            return ['status' => 'error', 'http_status' => null, 'mapped' => null, 'error' => 'Connection failed'];
        }
        if (in_array($res->status(), [404, 410], true)) {
            return ['status' => 'gone', 'http_status' => $res->status(), 'mapped' => null, 'error' => null];
        }
        if (! $res->successful()) {
            return ['status' => 'error', 'http_status' => $res->status(), 'mapped' => null, 'error' => 'HTTP '.$res->status()];
        }
        $raw = $this->parser->parseListing($res->body(), $url);
        // A removed listing redirects to a generic page without a listing number.
        if (! $raw['title'] || ($raw['specs']['İlan No'] ?? null) !== $this->idFromUrl($url)) {
            return ['status' => 'gone', 'http_status' => $res->status(), 'mapped' => null, 'error' => null];
        }

        return ['status' => 'ok', 'http_status' => $res->status(), 'mapped' => $this->mapper->map($raw), 'error' => null];
    }

    public function downloadImage(string $url): string
    {
        if (! preg_match('#^https://www\.kktcarabam\.com/uploads/images/[\w/.-]+\.(?:jpe?g|png|webp)$#i', $url)) {
            throw new RuntimeException('Image URL is not a source listing image.');
        }
        $res = $this->get($url, 30);
        if (! $res || ! $res->successful()) {
            throw new RuntimeException('Image download failed'.($res ? ' (HTTP '.$res->status().')' : ''));
        }

        return $res->body();
    }

    private function get(string $url, int $timeout = 20): ?Response
    {
        if ($this->blocked) {
            throw new RuntimeException('Source blocked automated access earlier in this run; stopping.');
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        if (! $this->robots()->allows($path)) {
            throw new RuntimeException("robots.txt disallows {$path}");
        }
        $this->throttle();
        try {
            $res = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'tr,en;q=0.8'])
                ->timeout($timeout)->get($url);
        } catch (ConnectionException) {
            return null;
        }
        if (in_array($res->status(), [403, 429, 503], true) || str_contains($res->body(), 'cf-challenge') || str_contains($res->body(), 'Sorry, you have been blocked')) {
            $this->blocked = true;
            throw new RuntimeException('Source returned HTTP '.$res->status().' (access restriction); stopping without retrying around it.');
        }

        return $res;
    }

    private function robots(): RobotsPolicy
    {
        if (! $this->robots) {
            $this->throttle();
            try {
                $res = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)->get('https://'.KktcarabamParser::HOST.'/robots.txt');
                $this->robots = RobotsPolicy::parse($res->successful() ? $res->body() : '', 'AIPriceListingImporter');
            } catch (ConnectionException) {
                $this->robots = RobotsPolicy::parse('', 'AIPriceListingImporter');
            }
        }

        return $this->robots;
    }

    private function throttle(): void
    {
        $wait = $this->lastRequestAt + $this->delayMs / 1000 - microtime(true);
        if ($wait > 0) {
            ($this->sleeper ?? fn (float $s) => usleep((int) ($s * 1_000_000)))($wait);
        }
        $this->lastRequestAt = microtime(true);
    }
}
