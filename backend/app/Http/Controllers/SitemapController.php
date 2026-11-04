<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Listing;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml for search engines: static pages, every active listing and
 * every published blog post. URLs point at the SPA (FRONTEND_URL), which is
 * the same origin in production. Cached briefly — it's crawled often.
 */
class SitemapController extends Controller
{
    private const STATIC_PAGES = [
        ['/', 'daily', '1.0'],
        ['/cars', 'hourly', '0.9'],
        ['/properties', 'hourly', '0.9'],
        ['/rentals', 'daily', '0.8'],
        ['/market-insights', 'daily', '0.6'],
        ['/investment-center', 'daily', '0.6'],
        ['/pricing', 'weekly', '0.5'],
        ['/blog', 'daily', '0.6'],
        ['/faq', 'weekly', '0.4'],
        ['/about', 'monthly', '0.3'],
        ['/privacy', 'yearly', '0.2'],
        ['/terms', 'yearly', '0.2'],
        ['/contact', 'monthly', '0.3'],
    ];

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(30), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $base = rtrim(config('app.frontend_url'), '/');
        $urls = [];

        foreach (self::STATIC_PAGES as [$path, $freq, $priority]) {
            $urls[] = $this->url($base.$path, null, $freq, $priority);
        }

        Listing::where('status', 'active')->select(['slug', 'updated_at'])->orderByDesc('updated_at')
            ->chunk(1000, function ($listings) use (&$urls, $base) {
                foreach ($listings as $l) {
                    $urls[] = $this->url("{$base}/listings/{$l->slug}", $l->updated_at, 'daily', '0.8');
                }
            });

        BlogPost::published()->select(['slug', 'updated_at'])->orderByDesc('published_at')->get()
            ->each(function ($p) use (&$urls, $base) {
                $urls[] = $this->url("{$base}/blog/{$p->slug}", $p->updated_at, 'monthly', '0.6');
            });

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $urls)."\n</urlset>\n";
    }

    private function url(string $loc, $lastmod, string $freq, string $priority): string
    {
        $loc = htmlspecialchars($loc, ENT_XML1);
        $mod = $lastmod ? "<lastmod>{$lastmod->toAtomString()}</lastmod>" : '';
        return "  <url><loc>{$loc}</loc>{$mod}<changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>";
    }
}
