<?php

namespace App\Services\Imports\Contracts;

/**
 * One source website. Implementations only read publicly rendered pages,
 * respect robots.txt and the source's configured request delay, and never
 * bypass logins, CAPTCHAs, bot protection or hidden contact reveals.
 */
interface SourceAdapter
{
    public function key(): string;

    /** @return array<string, string> source listing ID => URL, in discovery order */
    public function discover(int $maxPages): array;

    /**
     * @return array{status: 'ok'|'gone'|'blocked'|'error', http_status: ?int, mapped: ?array, error: ?string}
     */
    public function fetchListing(string $url): array;

    /** Raw image bytes; throws on failure or when the URL is not an allowed source image. */
    public function downloadImage(string $url): string;

    public function idFromUrl(string $url): ?string;

    public function canonicalUrl(string $url): ?string;
}
