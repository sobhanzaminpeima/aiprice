<?php

namespace App\Services\Imports\Kktcarabam;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns public KKTCarabam HTML into raw, un-normalized fields. Pure: no I/O.
 *
 * Only content rendered openly on the page is read. The seller phone number is
 * masked on the page and revealed by a separate click/AJAX call; this parser
 * reports the masked text and whether it was fully visible, and never calls
 * the reveal endpoint.
 */
final class KktcarabamParser
{
    public const HOST = 'www.kktcarabam.com';

    /** Listing URL ids found on an index/category page, in page order. */
    public function parseIndex(string $html): array
    {
        preg_match_all('#https://www\.kktcarabam\.com/(\d{5,})-([a-z0-9-]+)(?=["\'\s>])#', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as [$url, $id, $slug]) {
            // New vehicles and spare parts are out of scope for a used-car import.
            if (str_starts_with($slug, 'sifir-km') || isset($out[$id])) {
                continue;
            }
            $out[$id] = $url;
        }

        return $out;
    }

    public function hasNextPage(string $html, int $page): bool
    {
        return (bool) preg_match('#[?&]p='.($page + 1).'\b#', $html);
    }

    public static function idFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! in_array($parts['host'] ?? '', [self::HOST, 'kktcarabam.com'], true)) {
            return null;
        }

        return preg_match('#^/(\d{5,})-[a-z0-9-]+/?$#', $parts['path'] ?? '', $m) ? $m[1] : null;
    }

    public static function canonicalUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $id = self::idFromUrl($url);

        return $id ? 'https://'.self::HOST.rtrim($parts['path'], '/') : null;
    }

    public function parseListing(string $html, string $url): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $x = new DOMXPath($dom);

        $specs = [];
        foreach ($x->query("//div[contains(concat(' ',normalize-space(@class),' '),' orta ')]//table//tr") as $tr) {
            $th = $x->query('./th', $tr)->item(0);
            $td = $x->query('./td', $tr)->item(0);
            if ($th && $td) {
                $key = rtrim(self::text($th), ': ');
                $specs[$key] ??= self::text($td);
            }
        }

        $prices = [];
        // Only the listing's own price box; sidebar widgets reuse the "fiyat" class.
        foreach ($x->query("//div[contains(@class,'fiyat_bilgi')]//div[contains(concat(' ',normalize-space(@class),' '),' fiyat ')]") as $node) {
            $prices[] = self::text($node);
        }

        $breadcrumbs = [];
        foreach ($x->query("//ol[contains(@class,'breadcrumb')]/li") as $li) {
            $t = self::text($li);
            if ($t !== '') {
                $breadcrumbs[] = $t;
            }
        }

        $address = [];
        foreach ($x->query("//div[contains(@class,'adres_bilgi')]/a") as $a) {
            $address[] = self::text($a);
        }

        $images = [];
        foreach ($x->query("//div[contains(@class,'fotorama')]//a[@href]") as $a) {
            /** @var DOMElement $a */
            $href = $a->getAttribute('href');
            if (preg_match('#^https://www\.kktcarabam\.com/uploads/images/[\w/.-]+\.(?:jpe?g|png|webp)$#i', $href) && ! in_array($href, $images, true)) {
                $images[] = $href;
            }
        }
        if ($images === []) {
            $og = $x->query("//meta[@property='og:image']/@content")->item(0)?->nodeValue;
            if ($og && str_contains($og, '/uploads/images/')) {
                $images[] = $og;
            }
        }

        $description = null;
        foreach ($x->query("//div[contains(@class,'card')][div[contains(@class,'card-header')][normalize-space(.)='Açıklama']]/div[contains(@class,'card-body')]") as $body) {
            $description = self::multiline($body);
        }

        $features = [];
        foreach ($x->query("//div[contains(@class,'grup')]") as $group) {
            $title = self::text($x->query(".//div[contains(@class,'baslik')]", $group)->item(0));
            foreach ($x->query(".//div[contains(@class,'row')]/*", $group) as $item) {
                $t = self::text($item);
                if ($t !== '') {
                    $features[] = ['group' => $title, 'name' => $t];
                }
            }
        }

        $sellerBox = $x->query("//div[contains(@class,'sag') and contains(@class,'iletisim')]")->item(0);
        $sellerName = $sellerBox ? self::text($x->query('.//h4', $sellerBox)->item(0)) : '';
        $sellerProfile = $sellerBox ? $x->query(".//a[contains(@href,'/uye/')]/@href", $sellerBox)->item(0)?->nodeValue : null;
        $phoneText = $sellerBox ? self::text($x->query(".//*[contains(@class,'tel-no')]", $sellerBox)->item(0)) : '';

        $h1 = self::text($x->query('//h1')->item(0));

        return [
            'source_listing_id' => $specs['İlan No'] ?? self::idFromUrl($url),
            'url_id' => self::idFromUrl($url),
            'canonical_url' => self::canonicalUrl($url),
            'title' => $h1 !== '' ? $h1 : null,
            'breadcrumbs' => $breadcrumbs,
            'prices' => $prices,
            'address' => $address,
            'specs' => $specs,
            'description' => $description,
            'features' => $features,
            'images' => $images,
            'seller' => [
                'name' => $sellerName !== '' ? $sellerName : null,
                'profile_url' => $sellerProfile,
                'phone_text' => $phoneText !== '' ? $phoneText : null,
                // True only when the full number is printed on the page.
                'phone_fully_visible' => $phoneText !== '' && ! preg_match('/[*•]/u', $phoneText),
            ],
        ];
    }

    private static function text(?DOMNode $node): string
    {
        if (! $node) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function multiline(DOMNode $node): string
    {
        $lines = [];
        foreach ($node->childNodes as $child) {
            $t = self::text($child);
            if ($t !== '') {
                $lines[] = $t;
            }
        }

        return implode("\n", $lines);
    }
}
