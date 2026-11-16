<?php

namespace Tests\Support;

use App\Services\Imports\Contracts\SourceAdapter;
use App\Services\Imports\Kktcarabam\KktcarabamMapper;
use App\Services\Imports\Kktcarabam\KktcarabamParser;
use RuntimeException;

/** In-memory source running the real parser + mapper over fixture pages. */
final class FixtureAdapter implements SourceAdapter
{
    public int $requests = 0;

    public function __construct(private array $listings, private array $failImagesFor = [], private bool $blocked = false, private array $gone = []) {}

    public function key(): string
    {
        return 'kktcarabam';
    }

    public function discover(int $maxPages): array
    {
        $out = [];
        foreach ($this->listings as $l) {
            $out[$l['id']] = "https://www.kktcarabam.com/{$l['id']}-test-listing";
        }

        return $out;
    }

    public function fetchListing(string $url): array
    {
        $this->requests++;
        if ($this->blocked) {
            return ['status' => 'blocked', 'http_status' => 403, 'mapped' => null, 'error' => 'Source returned HTTP 403'];
        }
        $id = KktcarabamParser::idFromUrl($url);
        $l = collect($this->listings)->firstWhere('id', $id);
        if (! $l || in_array($id, $this->gone, true)) {
            return ['status' => 'gone', 'http_status' => 404, 'mapped' => null, 'error' => null];
        }
        $raw = (new KktcarabamParser)->parseListing(KktFixture::listingHtml($l), $url);

        return ['status' => 'ok', 'http_status' => 200, 'mapped' => (new KktcarabamMapper)->map($raw), 'error' => null];
    }

    public function downloadImage(string $url): string
    {
        if (preg_match('/img-(\d+)-(\d+)-/', $url, $m) === 1) {
            if (in_array($m[1], $this->failImagesFor, true)) {
                throw new RuntimeException('Image download failed (HTTP 500)');
            }
            // Position 2 repeats position 1 exactly, to exercise duplicate removal.
            $pos = (int) $m[2] === 2 ? 1 : (int) $m[2];

            return KktFixture::jpeg(((int) $m[1] % 1000) * 10 + $pos);
        }
        throw new RuntimeException('unexpected url');
    }

    public function idFromUrl(string $url): ?string
    {
        return KktcarabamParser::idFromUrl($url);
    }

    public function canonicalUrl(string $url): ?string
    {
        return KktcarabamParser::canonicalUrl($url);
    }

    public function forget(string $id): void
    {
        $this->gone[] = $id;
    }
}
