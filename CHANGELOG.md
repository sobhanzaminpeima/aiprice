# Changelog

All notable changes to AIPrice.app are documented here.

## 2026-11-28: Layout rebuilt to match the design mock-ups

### Changed
- Dark navy header on every public page with "Market Intelligence", a search button and a cyan "Post an ad".
- Home page rebuilt in the mock-up's order: left-aligned hero over a coastline illustration (`public/hero-coast.svg`: Beşparmak mountains, Kyrenia harbour, a villa), the "AI Decision Engine" search panel (Cars / Properties / Rentals, make or type, year or bedrooms, price, location, quick actions), feature tiles, "How AI price intelligence works" with a real analysed listing, a dark market-map panel with density legend, town prices and the overall 90-day change, "Best opportunities" cards, "Why AIPrice?", plan cards and the closing band.
- Listing cards: verdict badge on the photo (Good deal, Undervalued, Fair price, Slightly high, High price, AI estimate), price, specs and the AI fair range with confidence.
- Car and property pages rebuilt: page band, gallery card with breadcrumb, spec chips and ID, dark price card (asking price, fair range, deal signal, confidence, Send to seller, Save listing), full-width AI Deal Analysis, a row with similar listings, "Why this score?" and the risk insight, a horizontal specification row, description tabs, similar-listing cards, "See better AI-rated options", and a seller card with safety tips.
- `?mode=deal|find|invest` in the Cars and Properties URLs turns on AI ranking (used by the home search).

### Added
- `GET /api/v1/market/pulse`: typical asking-price change over the last 90 days across North Cyprus (null until both periods have enough listings).

### Fixed
- Similar-listing data now includes the listing type and fair range, so their cards render correctly.

## 2026-11-27: Redesign and market intelligence

### Added
- New look for the home page and the car and property pages: deep-navy hero with a coastline, glass feature tiles, a dark price card, a dark "AI Deal Analysis" panel, breadcrumbs (Cars › Brand › Model), a photo grid with "+N more" and a full-screen viewer, a "Why AIPrice?" row and a closing call-to-action band, plus a "Market Intelligence" footer column.
- **Market price trend chart** on every listing: median asking price per month of similar listings (same model, then brand, then category), from our own listings and collected market data. A month needs at least 3 prices and the chart at least 2 months; otherwise it says so instead of drawing a line.
- **Similar listings analysed** and **Data quality** (Good / Fair / Limited) next to the fair price range; the comparable count is now saved on each listing (`ai_comparable_count`).
- **Risk insight** (Low / Medium / High with a plain reason) from price versus similar listings, trust score, data available and open reports; saved as `risk_level` and `risk_note`.
- **Description tabs** (Original / English / Türkçe / فارسی): the AI translates each listing's description after approval (queued, and an hourly catch-up for older listings). Editing the text clears old translations. Needs an AI key; without one the tabs simply don't appear.
- **Area insights** on the home and Market Insights pages: listings, typical asking price and 90-day change per town (the change appears only when both periods have at least 3 listings).
- Listing cards show the AI fair range and confidence when the price check had enough data.
- Migration `2026_11_26_000001_add_market_intelligence_fields`.

## 2026-11-26: Posting time on every listing

### Added
- Every listing now shows when it was posted: relative ("5 minutes ago", "3 hours ago", "yesterday", "3 weeks ago") on cards, and relative plus the exact date and time (Cyprus time, Europe/Nicosia) on the listing page. Works in English, Turkish and Persian. Sellers see "Uploaded … · Live since …" in My Listings; the admin listings table shows when each listing was uploaded.

## 2026-11-26: Market research agent and clearer price-check wording

### Added
- **AI market research agent** (`MarketResearchAgent`): for any source added under Admin → Competitors that has no dedicated parser, it reads the public listing page(s), asks the AI provider to list the priced ads, and stores them as market comparables for the fair-price check. Safeguards: honours `robots.txt` (a blocked or erroring robots file means no crawl), same host only, at most 5 pages per source at ≥1.5 s apart, private/local addresses refused, only facts stored (price, currency, city, a few attributes, link back; no photos, descriptions or phone numbers), and any AI row whose price or link is not actually printed on the page, or whose numbers are implausible, is discarded. Ads a site stops showing are retired after 14 days. Optional `config.pages` (extra page URLs, same host) and `config.request_delay_ms` per source.

### Changed
- "Not enough data" on listing cards now reads "Waiting for similar listings", with a tooltip explaining that the price check switches on as more similar listings appear (English, Turkish, Persian).

## 2026-11-25: Launch pricing, plan limits and offline payments

### Added
- Separate plan ladders on `/pricing` (Private seller / Car dealer / Real estate agency tabs), in English, Turkish and Persian:
  - Private: free, 2 active listings, 10 photos, 60-day listings.
  - Dealer Start £19 (15 listings) · **Dealer Pro £49** (60, 5 featured, most popular) · Dealer Max £99 (200, 15 featured).
  - Agency Start £29 (25 listings) · **Agency Pro £69** (100, 5 featured, most popular) · Agency Elite £149 (300, 15 featured).
  - Yearly = 10 × monthly ("2 months free"); per-listing price shown on every paid plan.
- **One-month free trial** on every paid plan, once per account, no card. Sellers can switch plans during the trial; a reminder email goes out 3 days before it ends.
- **Offline payments** while no gateway is set up: a seller requests a plan, sees the payment instructions, and staff press *Record payment* in admin, which activates or extends the plan (starting when the trial/current period ends), creates a payment and a paid invoice, and emails the seller. Stripe checkout is still used automatically once configured.
- Admin **Plans & Subscriptions** page: edit every plan field (prices, limits, featured slots, listing lifetime, trial days, "most popular", on sale), waiting-for-payment queue, record payment / renew / change end date / cancel, give a plan by email, edit payment instructions, MRR and trial stats.
- Seller dashboard: plan usage bars, trial countdown, pending request, *Feature* / *Mark sold* / *Relist* on My Listings, pagination beyond 10 listings.
- `subscriptions:remind` scheduled command; `app:check-production` now also checks payment instructions and the free plan.

### Changed
- Listing limits, photo limits and featured slots are now **enforced** on every seller endpoint (they were only displayed before). Sold/rented/expired/rejected listings do not use a slot; relisting needs one.
- Listing lifetime on approval comes from the seller's plan instead of a fixed 90 days.
- Featured listings lead the default "newest" ordering.
- The previous Free/Starter/Professional/Enterprise plans are switched off (existing subscribers keep them until they end).

### Fixed
- The pricing page had no site header or footer.

## 2026-11-05: Admin inbox, languages, light redesign, real data only

### Added
- Admin **Requests inbox** (`/admin/inbox`): every user request arrives with the full text and the sender's name, email and phone: messages to sellers, contact form, support tickets and replies, new listings, identity checks and new accounts. Staff also get each one by email (`StaffAlert`).
- Turkish and Persian (right-to-left) interface with a language switcher in the header, mobile menu and footer. Fonts are self-hosted: Inter (Latin and Turkish) and Vazirmatn (Persian).
- AI fallback for the price check: when there are fewer than 3 comparable listings and an OpenAI key is set, an AI model gives a typical range. It is labelled "AI rough estimate", confidence is fixed at 30, and absurd answers are rejected.
- Privacy notice (`/privacy`) and terms of use (`/terms`), linked from the footer and the sign-up form.

### Changed
- Light theme redesign: navy brand hero, white cards with depth, navy footer, readable map controls.
- Admin sidebar grouped by task (Dashboard, Listings & catalog, Users & sellers, Messages & support, Pricing & AI, Money, Safety & content), collapsible, with page search, unread badge and a mobile drawer.
- Map: price-pill markers coloured by type, overlapping pins fanned out, empty state, and town names in Turkish, English or Greek form now map to coordinates (the real Famagusta listing had no pin).
- The SPA shell is served with `Cache-Control: no-cache`, so visitors see a new release straight away.
- The saved theme key changed, so every visitor starts on the new light theme once.

### Fixed
- Listing cards showed "Slightly high" for listings with no estimate; they now say "Not enough data".
- The rule-based AI summary called listings without data "priced above comparable listings".
- Property pages always listed "Air Con" and "Security" and invented distances to the airport and beach; only facts the seller gave are shown now.
- Engine size showed "1400L"; it now shows "1,400 cc".

### Removed
- The 32 sample listings with stock photos and invented prices (migration `2026_11_05_000001_remove_demo_listings`). Real listings are kept.

## 2026-11-04: Launch readiness

### Added
- "Send to seller / owner" on car and property pages; the seller gets an in-app and email notification. Buyer contact details are shared only when the buyer opts in.
- Seller phone privacy: guests see a masked number (last 3 digits hidden, enforced in the API). The full number comes from an authenticated, rate-limited endpoint, and every reveal is logged.
- bama.ir-style browsing: category rows (body type, quick filters, price classes, brands, property types, cities) on Home, Cars and Properties. A "Post an ad" button in the header.
- Working listing filters: brand, model, body type, fuel, transmission, year, mileage and property type (the backend previously ignored them, so menu links had no effect).
- Admin "AI Status" page: estimate coverage, queue health, market data sources, full re-score, and an OpenAI connection test.
- `BrandGuard`: every AI prompt and output and all imported text is kept on the AIPrice.app brand, with no third-party marketplace names or "verified/guaranteed price" claims.
- `docs/DEPLOYMENT_CHECKLIST.md`.

### Changed
- Light theme is now the default. Both themes use the brand palette (blue #159BF2, cyan #12C5E8, green #16C784, navy #081827), and light-mode text and accents meet WCAG AA contrast.
- AI price estimate rewritten: same-currency comparables only, similarity tiers (model/brand plus year, or property type, bedrooms and size), median with outlier trimming, competitor market data included, and "not enough data" instead of an invented verdict. Wording says "estimate", never "verified".
- Mega menu: hover gap bridge, 220 ms close delay, click/tap toggle, keyboard (focus, Esc) and ARIA support.
- Map tiles switched to OpenStreetMap with attribution (CARTO now watermarks keyless use).

### Removed
- Non-functional admin pages: Settings (nothing was saved), AI Intelligence, LLM Management (fake statistics; model registry unused) and AI Usage (logs never written).
- Crawler "sample data" fallback that inserted invented competitor prices.

### Security
- Fixed stored XSS in map popups (listing title, city and image were inserted as raw HTML).
- `listings.meta` and `import_source_key` are no longer serialized publicly.
- Global API rate limit (240/min per user or IP), and HSTS over HTTPS.
- `.claude/settings.local.json` (local paths and test credentials) removed from Git tracking.
- The sample-listing seeder refuses to run in production.

## 2026-09-26 — Production hardening and AIPrice rebrand

### Added

- AIPrice.app identity, refreshed logo assets, manifest, favicons, and application metadata
- Complete password reset, email verification, forced password change, auth throttling, and token revocation flows
- Endpoint-level staff permissions with full `super_admin` access and restricted specialist roles
- Queue-backed AI analysis, competitor crawling, notifications, announcements, and scheduled maintenance
- Real admin modules for finance, market analytics, live analytics, fraud, support, reports, content, and announcements
- Stripe-ready payment gateway abstraction, checkout, invoices, subscriptions, and signed webhook processing
- Public blog, FAQ, contact, market insights, investment center, and honest empty states
- Canonical metadata, Open Graph, JSON-LD, sitemap, crawler rules, and visit deduplication
- Shared-host-compatible release builder, database/file backup, maintenance mode, migrations, caching, and rollback

### Changed

- Replaced interface emoji with Lucide icons and hardened responsive navigation
- Limited the bottom navigation bar to mobile breakpoints
- Improved vehicle and property cards, listing details, maps, gallery behavior, filtering, CTA placement, and accessibility
- Replaced mock analytics and placeholder admin data with persisted application data

### Verified

- Frontend production build, TypeScript, ESLint, and Oxlint
- Backend test suite: 60 tests and 279 assertions
- Desktop and mobile smoke tests covering public pages, listing details, overflow, and console errors

### Release

- Production revision: `bef35f1`
