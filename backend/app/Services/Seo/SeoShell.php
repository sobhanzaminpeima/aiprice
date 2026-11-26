<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\Listing;
use App\Models\Property;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Server-side SEO for the single-page app. The SPA shell is the same file for
 * every URL, so without this every page would reach crawlers and link previews
 * with one generic title and an empty body.
 *
 * For each request it fills in the real <title>, description, canonical URL,
 * Open Graph/Twitter tags, robots and JSON-LD, and puts readable HTML (headline,
 * intro, listing links) inside #app. Vue replaces that HTML as soon as it boots,
 * so visitors see the normal app; crawlers that don't run JavaScript still get
 * the content and the links.
 */
class SeoShell
{
    private const SITE = 'AIPrice.app';

    /** Paths that must never be indexed (also disallowed in robots.txt). */
    private const PRIVATE = ['admin', 'dashboard', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'change-password', 'billing'];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4?: string}> path => [title, description, h1, intro, listing type] */
    private const PAGES = [
        '' => ['Cars & Property for Sale in North Cyprus with AI Price Checks', 'Buy and sell cars, homes and rentals in North Cyprus (KKTC). Every listing gets an AI fair-price check against similar listings. Kıbrıs satılık araba ve emlak ilanları.', 'Find the best price for cars and property in North Cyprus', 'AIPrice.app is the North Cyprus marketplace where every car, home and rental is checked against similar listings by AI, so you see the fair price before you buy or sell.'],
        'cars' => ['Cars for Sale in North Cyprus – Used & New | AI Price Check', 'Used and new cars for sale in Girne, Lefkoşa, Gazimağusa, İskele and across North Cyprus, each with an AI fair-price check. KKTC satılık ikinci el araba ilanları.', 'Cars for sale in North Cyprus', 'Browse used and new cars for sale across North Cyprus. Each listing shows an AI fair-price range, a price battery and a confidence score based on similar cars.', 'vehicle'],
        'properties' => ['Property for Sale in North Cyprus – Villas, Apartments & Land', 'Apartments, villas, houses and land for sale in North Cyprus with AI price verdicts against comparable listings. KKTC satılık daire, villa ve arsa ilanları.', 'Property for sale in North Cyprus', 'Find apartments, villas, houses and land for sale in Kyrenia, Nicosia, Famagusta, İskele and the rest of North Cyprus, each with an AI fair-price check.', 'property'],
        'rentals' => ['Rentals in North Cyprus – Homes & Apartments to Rent', 'Homes and apartments to rent in North Cyprus, with AI checks on the asking rent. KKTC kiralık daire ve ev ilanları.', 'Rentals in North Cyprus', 'Homes and apartments to rent across North Cyprus, with an AI check on whether the asking rent is fair.', 'rental'],
        'search' => ['Search Cars & Property in North Cyprus', 'Search every car, property and rental listing in North Cyprus, ranked by AI for price, fit and investment potential.', 'Search listings in North Cyprus', 'Search cars, property and rentals across North Cyprus and rank them by best deal, best fit or investment potential.'],
        'pricing' => ['Pricing for Dealers, Agencies & Private Sellers', 'Free listings for private sellers. Plans for car dealers and estate agencies in North Cyprus from £19/month, first month free.', 'Plans for every seller', 'Private sellers list free. Car dealers and estate agencies can try any plan free for a month.'],
        'market-insights' => ['North Cyprus Market Insights – Car & Property Prices', 'Typical asking prices, price trends and area insights for cars and property in North Cyprus, from live listings.', 'North Cyprus market insights', 'Typical asking prices, price changes and the most active towns, from every live listing on AIPrice.app.'],
        'investment-center' => ['Property Investment in North Cyprus – AI Investment Scores', 'Compare North Cyprus property by AI investment score, price against the market and risk.', 'Property investment in North Cyprus', 'Compare property by investment score, price against similar listings and risk signals.'],
        'about' => ['About AIPrice.app', 'AIPrice.app is an AI-powered marketplace for cars and property in North Cyprus.', 'About AIPrice.app', 'We help buyers and sellers in North Cyprus see the fair price of cars and property, using real listing data.'],
        'faq' => ['FAQ – Buying & Selling in North Cyprus', 'Answers to common questions about buying, selling and AI pricing on AIPrice.app.', 'Frequently asked questions', 'How the AI price check works, how to list, and how to buy safely in North Cyprus.'],
        'contact' => ['Contact AIPrice.app', 'Get in touch with the AIPrice.app team about listings, accounts, pricing or partnerships.', 'Contact us', 'Questions about listings, plans or partnerships? Send us a message.'],
        'blog' => ['Blog – North Cyprus Car & Property Market Guides', 'Market analysis and guides for buying and selling cars and property in North Cyprus.', 'AIPrice.app blog', 'Guides and market analysis for buying and selling in North Cyprus.'],
        'privacy' => ['Privacy notice', 'How AIPrice.app collects and uses personal data.', 'Privacy notice', 'How we handle your data.'],
        'terms' => ['Terms of use', 'The terms for using AIPrice.app.', 'Terms of use', 'The rules for using the marketplace.'],
    ];

    /** Mirrors frontend/src/components/seoContent.ts so the FAQ markup matches the visible FAQ. */
    private const FAQ = [
        '' => [
            ['How does the AI price check work?', 'We compare the asking price with similar listings (same model, year, size and area) and show the typical range. With fewer than three similar listings we show no verdict rather than a guess.'],
            ['What is the price battery?', 'A score from 0 to 100% showing how good the price is against similar listings. A full battery means one of the best-value prices on the market.'],
            ['Is it free to list?', 'Yes. Private sellers can have two listings live for free. Dealers and estate agencies can try any plan free for a month.'],
        ],
        'cars' => [
            ['How do I know if a car price is fair?', 'Open the listing: the AI fair price range comes from similar cars of the same model and age, and the price battery shows how the price compares.'],
            ['Can I sell my car for free?', 'Yes. Private sellers list for free, and every listing gets an instant AI price check.'],
            ['What should I check before buying a used car?', 'See the car in person, check the registration documents and service history, and never pay before viewing it.'],
        ],
        'properties' => [
            ['How is the fair price of a property estimated?', 'We compare it with similar properties of the same type, size and number of bedrooms, and show the typical range of asking prices.'],
            ['Can foreigners buy property in North Cyprus?', 'Yes, foreign buyers can buy property, subject to permission from the authorities. Always use a licensed lawyer and check the title deed.'],
            ['What should I check before buying?', 'Check the title deed type, planning permits and any debts on the property with a lawyer before paying a deposit.'],
        ],
    ];

    private const CITIES = ['Girne' => 'Girne (Kyrenia)', 'Lefkosa' => 'Lefkoşa (Nicosia)', 'Gazimağusa' => 'Gazimağusa (Famagusta)', 'Iskele' => 'İskele', 'Guzelyurt' => 'Güzelyurt', 'Lapta' => 'Lapta'];

    public function __construct(private readonly string $origin)
    {
    }

    /** @return array{html: string, status: int} */
    public function render(string $shell, string $path): array
    {
        $path = trim($path, '/');
        $page = $this->resolve($path);

        return ['html' => $this->inject($shell, $page), 'status' => $page['status']];
    }

    private function resolve(string $path): array
    {
        $first = explode('/', $path)[0];
        if (in_array($first, self::PRIVATE, true)) {
            return $this->page('AIPrice.app', 'AIPrice.app', $path, noindex: true);
        }
        if (preg_match('#^listings/([^/]+)$#', $path, $m)) {
            return $this->listing($m[1], $path);
        }
        if (preg_match('#^blog/([^/]+)$#', $path, $m)) {
            return $this->blogPost($m[1], $path);
        }
        if (isset(self::PAGES[$path])) {
            [$title, $desc, $h1, $intro] = self::PAGES[$path];
            $type = self::PAGES[$path][4] ?? null;
            $faq = self::FAQ[$path] ?? [];
            $body = $this->heading($h1, $intro).$this->listingLinks($type, $path === '' ? 12 : 24)
                .($faq ? '<section><h2>Frequently asked questions</h2>'.collect($faq)->map(fn ($q) => '<h3>'.e($q[0]).'</h3><p>'.e($q[1]).'</p>')->implode('').'</section>' : '')
                .$this->categoryLinks();
            $ld = $path === '' ? $this->siteLd() : ($type ? [$this->breadcrumbLd([[$h1, "/{$path}"]])] : []);
            if ($faq) {
                $ld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)];
            }
            $ld = $ld ?: null;

            return $this->page($title, $desc, $path, body: $body, jsonLd: $ld, fullTitle: $path === '');
        }

        // Unknown URL: the SPA shows its own 404; tell crawlers too.
        return $this->page('Page not found', 'This page does not exist.', $path, noindex: true, status: 404);
    }

    private function listing(string $slug, string $path): array
    {
        $l = Listing::with('listable')->where('slug', $slug)->where('status', 'active')->first();
        if (! $l) {
            return $this->page('Listing not available', 'This listing has been sold, rented or removed.', $path, noindex: true, status: 404,
                body: $this->heading('This listing is no longer available', 'It may have been sold, rented or removed by the seller.').$this->categoryLinks());
        }
        $l->listable instanceof Vehicle && $l->listable->loadMissing('brand:id,name', 'model:id,name');
        $l->listable instanceof Property && $l->listable->loadMissing('propertyType:id,name');

        $price = $this->money((float) $l->price, (string) $l->currency);
        $city = $l->city ?: 'North Cyprus';
        $kind = match ($l->type) { 'vehicle' => 'for sale', 'rental' => 'to rent', default => 'for sale' };
        $title = Str::limit("{$l->title} {$kind} in {$city} – {$price}", 62, '');
        $facts = $this->facts($l);
        $desc = Str::limit(trim("{$l->title}, {$price}, {$city}. ".implode(', ', $facts).'. '.$this->verdictText($l).' '.strip_tags((string) $l->description)), 158);
        $image = $l->thumbnail ?: ($l->images[0] ?? null);
        $section = match ($l->type) { 'vehicle' => ['Cars', '/cars'], 'rental' => ['Rentals', '/rentals'], default => ['Properties', '/properties'] };

        $body = '<article><h1>'.e($l->title).'</h1>'
            .'<p><strong>'.e($price).'</strong> · '.e($city).', North Cyprus</p>'
            .($facts ? '<ul>'.implode('', array_map(fn ($f) => '<li>'.e($f).'</li>', $facts)).'</ul>' : '')
            .($this->verdictText($l) ? '<p>'.e($this->verdictText($l)).'</p>' : '')
            .($l->description ? '<p>'.nl2br(e(Str::limit(strip_tags((string) $l->description), 1200))).'</p>' : '')
            .'<p><a href="'.e($section[1]).'">'.e("More {$section[0]} in North Cyprus").'</a></p></article>'
            .$this->categoryLinks();

        $offer = ['@type' => 'Offer', 'price' => (float) $l->price, 'priceCurrency' => $l->currency, 'availability' => 'https://schema.org/InStock',
            'url' => $this->url("/listings/{$l->slug}"), 'areaServed' => $city];
        $item = ['@context' => 'https://schema.org', '@type' => $l->type === 'vehicle' ? 'Car' : 'Product', 'name' => $l->title,
            'description' => Str::limit(strip_tags((string) $l->description) ?: $desc, 500), 'sku' => $l->listing_number, 'offers' => $offer];
        if ($image) {
            $item['image'] = [$this->url($image)];
        }
        if ($l->listable instanceof Vehicle) {
            $v = $l->listable;
            $item += array_filter([
                'brand' => $v->brand?->name ? ['@type' => 'Brand', 'name' => $v->brand->name] : null,
                'model' => $v->model?->name, 'vehicleModelDate' => $v->year ? (string) $v->year : null,
                'mileageFromOdometer' => $v->mileage ? ['@type' => 'QuantitativeValue', 'value' => (int) $v->mileage, 'unitCode' => 'KMT'] : null,
                'fuelType' => $v->fuel_type, 'vehicleTransmission' => $v->transmission, 'bodyType' => $v->body_type,
            ]);
        }
        $crumbs = $this->breadcrumbLd([[$section[0], $section[1]], [$l->title, "/listings/{$l->slug}"]]);

        return $this->page($title, $desc, $path, body: $body, jsonLd: [$item, $crumbs], image: $image, type: 'product');
    }

    private function blogPost(string $slug, string $path): array
    {
        $post = BlogPost::where('slug', $slug)->where('status', 'published')->first();
        if (! $post) {
            return $this->page('Article not found', 'This article does not exist.', $path, noindex: true, status: 404);
        }
        $desc = Str::limit(strip_tags((string) ($post->excerpt ?: $post->content)), 158);
        $body = '<article><h1>'.e($post->title).'</h1><p>'.e(Str::limit(strip_tags((string) $post->content), 2000)).'</p></article>'.$this->categoryLinks();
        $ld = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post->title, 'description' => $desc,
            'datePublished' => $post->published_at?->toIso8601String(), 'publisher' => ['@type' => 'Organization', 'name' => self::SITE]];

        return $this->page($post->title, $desc, $path, body: $body, jsonLd: $ld, image: $post->featured_image, type: 'article');
    }

    // ---- Building blocks ---------------------------------------------------

    private function page(string $title, string $desc, string $path, bool $noindex = false, int $status = 200, string $body = '', array|null $jsonLd = null, ?string $image = null, string $type = 'website', bool $fullTitle = false): array
    {
        // Google shows about 60 characters: add the site name only when it fits.
        $full = $title === self::SITE ? $title : (mb_strlen($title) <= 46 || $fullTitle && mb_strlen($title) <= 46 ? "{$title} | ".self::SITE : $title);

        return ['title' => $full, 'description' => $desc, 'canonical' => $this->url('/'.$path),
            'noindex' => $noindex, 'status' => $status, 'body' => $body, 'jsonLd' => $jsonLd, 'image' => $image ? $this->url($image) : $this->url('/icon-512.png'), 'type' => $type];
    }

    private function heading(string $h1, string $intro): string
    {
        return '<header><h1>'.e($h1).'</h1><p>'.e($intro).'</p></header>';
    }

    private function listingLinks(?string $type, int $limit): string
    {
        $rows = Listing::where('status', 'active')->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('published_at')->limit($limit)->get(['slug', 'title', 'price', 'currency', 'city']);
        if ($rows->isEmpty()) {
            return '';
        }

        return '<section><h2>Latest listings</h2><ul>'.$rows->map(fn ($l) => '<li><a href="/listings/'.e($l->slug).'">'.e($l->title).'</a> – '.e($this->money((float) $l->price, (string) $l->currency)).($l->city ? ' · '.e($l->city) : '').'</li>')->implode('').'</ul></section>';
    }

    private function categoryLinks(): string
    {
        $links = ['/cars' => 'Cars for sale in North Cyprus', '/properties' => 'Property for sale in North Cyprus', '/rentals' => 'Rentals in North Cyprus', '/market-insights' => 'Market insights', '/pricing' => 'Plans for sellers'];
        $html = '<nav aria-label="Browse"><ul>'.collect($links)->map(fn ($t, $u) => '<li><a href="'.e($u).'">'.e($t).'</a></li>')->implode('');
        foreach (self::CITIES as $q => $label) {
            $html .= '<li><a href="/cars?city='.rawurlencode($q).'">'.e("Cars in {$label}").'</a></li><li><a href="/properties?city='.rawurlencode($q).'">'.e("Property in {$label}").'</a></li>';
        }

        return $html.'</ul></nav>';
    }

    /** @return list<string> */
    private function facts(Listing $l): array
    {
        $x = $l->listable;
        if ($x instanceof Vehicle) {
            return array_values(array_filter([$x->year ? (string) $x->year : null, $x->mileage ? number_format((int) $x->mileage).' km' : null, $x->fuel_type ? ucfirst($x->fuel_type) : null, $x->transmission ? ucfirst($x->transmission) : null, $x->body_type ? ucfirst($x->body_type) : null]));
        }

        return array_values(array_filter([
            isset($x->bedrooms) && $x->bedrooms !== null ? $x->bedrooms.' bedroom'.($x->bedrooms == 1 ? '' : 's') : null,
            ! empty($x->bathrooms) ? $x->bathrooms.' bathroom'.($x->bathrooms == 1 ? '' : 's') : null,
            ! empty($x->area_sqm) ? rtrim(rtrim((string) $x->area_sqm, '0'), '.').' m²' : null,
            $x instanceof Property && $x->propertyType?->name ? $x->propertyType->name : null,
        ]));
    }

    private function verdictText(Listing $l): string
    {
        if (! $l->market_min_price || ! $l->market_max_price || $l->ai_price_status === 'insufficient_data') {
            return '';
        }
        $range = $this->money((float) $l->market_min_price, (string) $l->currency).'–'.$this->money((float) $l->market_max_price, (string) $l->currency);

        return "AI fair price range: {$range}".($l->price_battery_percent !== null ? ', price battery '.(int) $l->price_battery_percent.'%' : '').'.';
    }

    private function siteLd(): array
    {
        return [
            ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => self::SITE, 'url' => $this->url('/'), 'logo' => $this->url('/icon-512.png'), 'areaServed' => 'North Cyprus'],
            ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => self::SITE, 'url' => $this->url('/'),
                'potentialAction' => ['@type' => 'SearchAction', 'target' => $this->url('/search').'?search={search_term_string}', 'query-input' => 'required name=search_term_string']],
        ];
    }

    private function breadcrumbLd(array $items): array
    {
        $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $this->url('/')]];
        foreach ($items as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => $this->url($url)];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    private function inject(string $shell, array $p): string
    {
        $tags = '<meta name="description" content="'.e($p['description']).'">'
            .'<meta name="robots" content="'.($p['noindex'] ? 'noindex, nofollow' : 'index, follow, max-image-preview:large').'">'
            .'<link rel="canonical" href="'.e($p['canonical']).'">'
            .'<meta property="og:site_name" content="'.self::SITE.'"><meta property="og:type" content="'.e($p['type']).'">'
            .'<meta property="og:title" content="'.e($p['title']).'"><meta property="og:description" content="'.e($p['description']).'">'
            .'<meta property="og:url" content="'.e($p['canonical']).'"><meta property="og:image" content="'.e($p['image']).'">'
            .'<meta property="og:locale" content="en_GB"><meta property="og:locale:alternate" content="tr_TR"><meta property="og:locale:alternate" content="fa_IR">'
            .'<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="'.e($p['title']).'">'
            .'<meta name="twitter:description" content="'.e($p['description']).'"><meta name="twitter:image" content="'.e($p['image']).'">'
            .'<meta name="geo.region" content="CY"><meta name="geo.placename" content="North Cyprus">'
            // Readable (not hidden) until the app replaces it a moment later.
            .'<style>.seo-prerender{max-width:72rem;margin:0 auto;padding:2rem 1rem;font-family:system-ui,sans-serif;color:#0b1726;line-height:1.5}.seo-prerender a{color:#0567b5}.seo-prerender ul{padding-left:1.2rem}</style>';
        if ($p['jsonLd']) {
            foreach (array_is_list($p['jsonLd']) ? $p['jsonLd'] : [$p['jsonLd']] as $ld) {
                // "<" escaped so the JSON can never close the script tag.
                $tags .= '<script type="application/ld+json" data-seo="server">'.str_replace('<', '<', json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'</script>';
            }
        }

        $html = preg_replace('#<title>.*?</title>#s', '<title>'.e($p['title']).'</title>', $shell, 1) ?? $shell;
        $html = preg_replace('#<meta name="description"[^>]*>\s*#', '', $html, 1) ?? $html;
        $html = str_replace('</head>', $tags.'</head>', $html);
        if ($p['body'] !== '') {
            $html = str_replace('<div id="app"></div>', '<div id="app"><div class="seo-prerender">'.$p['body'].'</div></div>', $html);
        }

        return $html;
    }

    private function url(string $path): string
    {
        return preg_match('#^https?://#', $path) ? $path : rtrim($this->origin, '/').'/'.ltrim($path, '/');
    }

    private function money(float $v, string $currency): string
    {
        $symbol = ['GBP' => '£', 'EUR' => '€', 'USD' => '$'][$currency] ?? '';

        return $symbol ? $symbol.number_format($v) : number_format($v).' '.$currency;
    }
}
