<template>
  <MainLayout>
    <!-- HERO: coastline scene, left-aligned headline -->
    <section class="hero relative overflow-hidden px-4">
      <div class="max-w-7xl mx-auto relative z-10 pt-14 sm:pt-20 pb-44 md:pb-48">
        <div class="max-w-2xl">
          <span class="hero-badge inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold mb-5">✦ {{ $t('AI-powered market intelligence for North Cyprus') }}</span>
          <h1 class="hero-title text-4xl sm:text-6xl font-black leading-[1.05] mb-4">{{ $t('Find the best price') }} <span class="hero-accent">{{ $t('with AI') }}</span></h1>
          <p class="hero-sub text-base sm:text-lg mb-5">{{ $t('Real-time AI valuation, fair-price checks, market trends and intelligent comparisons for cars, properties and rentals in North Cyprus.') }}</p>
          <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm" style="color:#dce9f7">
            <li v-for="c in heroChecks" :key="c" class="flex items-center gap-2"><span style="color:#4fe0b0;font-weight:900">✓</span>{{ $t(c) }}</li>
          </ul>

          <!-- Welcome back: the visitor's last saved search -->
          <div v-if="lastPreference" class="mt-6 inline-flex items-center gap-3 rounded-2xl px-4 py-3" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16)">
            <span class="text-xs" style="color:#dce9f7">{{ $t('Welcome back — your last search') }}: <strong>{{ $t(lastPreference.asset_type) }}</strong><span v-if="lastPreference.city"> · {{ lastPreference.city }}</span><template v-if="newMatchesCount > 0"> · <strong style="color:#4fe0b0">{{ $t('{n} new matches', { n: newMatchesCount }) }}</strong></template></span>
            <button @click="rerunLastSearch" class="px-3 py-1.5 rounded-lg text-xs font-bold" style="background:linear-gradient(135deg,#12C5E8,#4fe0b0);color:#04111d">{{ $t('Show') }}</button>
          </div>
        </div>
      </div>
    </section>

    <!-- AI DECISION ENGINE (overlaps the hero) -->
    <div class="px-4 max-w-7xl mx-auto relative z-20 -mt-36 md:-mt-40">
      <DecisionEngine />
      <div v-if="activeCount === 0" class="mt-4 rounded-2xl p-4 text-sm flex flex-wrap items-center justify-between gap-3" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
        <span style="color:var(--text-secondary)">{{ $t('We\'re just launching in North Cyprus') }} — {{ $t('List your car or property free and get an instant AI price check — early listings get the most visibility.') }}</span>
        <RouterLink :to="sellLink" class="px-4 py-2 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('Post a free ad') }}</RouterLink>
      </div>
    </div>

    <div class="pt-6"><AiFeatureTiles /></div>

    <div class="pt-12"><HowAiWorks :example="example" /></div>

    <MarketMapPanel />

    <!-- TOP 10 SPONSORED LISTINGS (Advertisements) -->
    <section v-if="ads.length > 0" class="px-4 pb-16 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(var(--warning-rgb),0.1);border:1px solid rgba(var(--warning-rgb),0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="warning" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">{{ $t('Top 10') }} <span style="color:var(--warning)">{{ $t('Sponsored') }}</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t('Premium promoted listings') }}</p>
          </div>
        </div>
        <RouterLink to="/search?sponsored=1" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('View all →') }}</RouterLink>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <div v-for="(ad, i) in ads.slice(0,10)" :key="ad.id"
          class="rounded-2xl overflow-hidden cursor-pointer transition-all hover:scale-[1.02]"
          style="background:var(--bg-surface);border:1px solid rgba(var(--warning-rgb),0.2)"
          @click="trackAdClick(ad)">
          <div class="relative aspect-[4/3] overflow-hidden" style="background:var(--surface-overlay-soft)">
            <img v-if="ad.listing?.thumbnail" :src="ad.listing.thumbnail" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="muted" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            </div>
            <div class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-bold" style="background:var(--warning);color:var(--on-accent)">#{{ i+1 }}</div>
          </div>
          <div class="p-3">
            <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ ad.listing?.title || ad.name }}</p>
            <p class="text-sm font-black mt-1" style="color:var(--warning)">{{ ad.listing?.price ? fmt(ad.listing.price, ad.listing.currency) : 'Sponsored' }}</p>
          </div>
        </div>
      </div>
    </section>


    <!-- AI-CURATED OPPORTUNITIES -->
    <section class="px-4 pb-16 max-w-7xl mx-auto" aria-labelledby="opps-heading">
      <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
          <p class="text-xs font-bold tracking-wider uppercase mb-1" style="color:var(--accent-cyan)">⚡ {{ $t('AI-curated opportunities') }}</p>
          <h2 id="opps-heading" class="text-2xl sm:text-3xl font-black" style="color:var(--text-primary)">{{ $t('Best opportunities in North Cyprus') }}</h2>
        </div>
        <RouterLink to="/search?mode=deal" class="text-sm font-semibold" style="color:var(--accent-cyan)">{{ $t('View all AI-analysed listings') }} →</RouterLink>
      </div>
      <div v-if="loadingOpps" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-72 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else-if="opportunities.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in opportunities" :key="l.id" :listing="l" />
      </div>
      <div v-else class="rounded-2xl p-10 text-center" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
        <p class="font-bold mb-1" style="color:var(--text-primary)">{{ $t('No listings yet') }}</p>
        <p class="text-sm mb-4" style="color:var(--text-muted)">{{ $t('Be the first — your listing gets an instant AI price check.') }}</p>
        <RouterLink :to="sellLink" class="inline-block px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('Post a free ad') }}</RouterLink>
      </div>
    </section>

    <ListingRail id="rail-newest" icon="✦" eyebrow="Just listed" title="Newest listings" sort="newest" />
    <ListingRail id="rail-popular" icon="◉" eyebrow="Popular now" title="Most viewed" sort="most_viewed" />
    <ListingRail id="rail-value" icon="▮" eyebrow="Price battery" title="Best value by price battery" sort="battery" :extra="{ ai: 'checked' }" />

    <WhyAiPrice />
    <SeoContent kind="home" />

    <!-- SELL ON AIPRICE: one card per kind of seller, details on /pricing -->
    <section class="px-4 pb-24 max-w-7xl mx-auto" aria-labelledby="sell-heading">
      <div class="text-center mb-10">
        <h2 id="sell-heading" class="text-3xl font-black mb-3" style="color:var(--text-primary)">{{ $t('Start Selling') }} <span class="gradient-text">{{ $t('Today') }}</span></h2>
        <p class="text-base max-w-xl mx-auto" style="color:var(--text-muted)">{{ $t('Private sellers list free. Dealers and agencies get their first month free — no card needed.') }}</p>
      </div>
      <div v-if="loadingPlans" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="h-56 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <RouterLink v-for="g in sellerGroups" :key="g.type" :to="{ path: '/pricing', query: { audience: g.type } }" class="rounded-2xl p-6 flex flex-col transition-transform hover:-translate-y-0.5"
          :style="g.type === 'dealer' ? 'background:var(--bg-surface);border:2px solid rgba(var(--accent-teal-rgb),0.4)' : 'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
          <h3 class="font-black text-lg mb-1" style="color:var(--text-primary)">{{ $t(g.title) }}</h3>
          <p class="text-xs mb-4" style="color:var(--text-muted)">{{ $t(g.blurb) }}</p>
          <p class="mb-1">
            <span v-if="g.from === 0" class="text-3xl font-black" style="color:var(--text-primary)">{{ $t('Free') }}</span>
            <template v-else-if="g.from !== null">
              <span class="text-xs" style="color:var(--text-muted)">{{ $t('from') }} </span>
              <span class="text-3xl font-black" style="color:var(--text-primary)">£{{ g.from }}</span>
              <span class="text-sm" style="color:var(--text-muted)">{{ $t('/month') }}</span>
            </template>
          </p>
          <p class="text-xs mb-6 flex-1" style="color:var(--accent-teal)">{{ g.from === 0 ? $t('{n} listings live at a time', { n: g.listings }) : $t('First month free · up to {n} listings', { n: g.listings }) }}</p>
          <span class="block text-center py-3 rounded-xl font-bold text-sm" :style="g.type === 'dealer' ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)' : 'background:var(--border-subtle);color:var(--text-secondary);border:1px solid var(--border-strong)'">
            {{ g.from === 0 ? $t('Post a free ad') : $t('See plans') }}
          </span>
        </RouterLink>
      </div>
    </section>
    <CtaBand />
  </MainLayout>
</template>

<script setup lang="ts">
import { usePageMeta } from '@/composables/usePageMeta'
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ListingCard from '@/components/listings/ListingCard.vue'
import AiFeatureTiles from '@/components/home/AiFeatureTiles.vue'
import DecisionEngine from '@/components/home/DecisionEngine.vue'
import HowAiWorks from '@/components/home/HowAiWorks.vue'
import MarketMapPanel from '@/components/home/MarketMapPanel.vue'
import WhyAiPrice from '@/components/home/WhyAiPrice.vue'
import SeoContent from '@/components/SeoContent.vue'
import ListingRail from '@/components/home/ListingRail.vue'
import CtaBand from '@/components/home/CtaBand.vue'
import { useAuthStore } from '@/stores/auth'
import client from '@/api/client'
import type { Listing } from '@/types'

usePageMeta(() => ({ title: undefined, description: 'Buy and sell cars, homes and rentals in North Cyprus (KKTC). Every listing gets an AI fair-price check and a price battery against similar listings.' }))

const router = useRouter()
const auth = useAuthStore()

const heroChecks = ['Know the real market value', 'Compare listings intelligently', 'Choose the best price with confidence']
const lastPreference = ref<{ asset_type: string; budget_max: number | null; city: string | null } | null>(null)
const newMatchesCount = ref(0)

function rerunLastSearch() {
  if (!lastPreference.value) return
  const params = new URLSearchParams()
  params.set('type', lastPreference.value.asset_type)
  if (lastPreference.value.budget_max) params.set('budget_max', String(lastPreference.value.budget_max))
  if (lastPreference.value.city) params.set('city', lastPreference.value.city)
  router.push('/search?' + params.toString())
}

const opportunities = ref<Listing[]>([])
const loadingOpps = ref(true)
// The "how it works" example is a real listing with a completed price check, never a mock-up.
const example = computed(() => opportunities.value
  .filter((l) => l.market_min_price && l.market_max_price && l.ai_price_status && l.ai_price_status !== 'insufficient_data')
  .sort((a, b) => Number(b.ai_comparable_count ?? 0) - Number(a.ai_comparable_count ?? 0))[0] ?? null)

const plans = ref<any[]>([])
// Cheapest price and largest listing allowance per kind of seller, for the "Start selling" cards.
const sellerGroups = computed(() => ([
  { type: 'seller', title: 'Private seller', blurb: 'Sell your own car or home.' },
  { type: 'dealer', title: 'Car dealer', blurb: 'Put your whole showroom online with AI price checks.' },
  { type: 'agency', title: 'Real estate agency', blurb: 'List your portfolio and stand out with featured listings.' },
]).map((g) => {
  const ps = plans.value.filter((p) => p.type === g.type)
  return {
    ...g,
    from: ps.length ? Math.min(...ps.map((p) => Number(p.price_monthly))) : null,
    listings: ps.length ? Math.max(...ps.map((p) => Number(p.max_listings))) : 0,
  }
}))
const ads = ref<any[]>([])
const loadingPlans = ref(true)
const activeCount = ref<number | null>(null)
const sellLink = computed(() => (auth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { redirect: '/dashboard/listings/new' } }))

onMounted(async () => {
  if (auth.isAuthenticated) {
    client.get('/me/search-preferences/last').then(({ data }) => {
      if (data.preference) {
        lastPreference.value = data.preference
        newMatchesCount.value = data.new_matches ?? 0
      }
    }).catch(() => {})
  }

  const [statsRes, oppsRes, plansRes, adsRes] = await Promise.allSettled([
    client.get('/market/stats'),
    // Ranked by the deal profile: best value for money first.
    client.get('/listings', { params: { mode: 'deal', per_page: 8 } }),
    client.get('/subscription-plans'),
    client.get('/advertisements?slot=top10'),
  ])
  if (statsRes.status === 'fulfilled') activeCount.value = statsRes.value.data?.active_listings ?? 0
  if (oppsRes.status === 'fulfilled') opportunities.value = (oppsRes.value.data?.data || []).slice(0, 8)
  loadingOpps.value = false
  if (plansRes.status === 'fulfilled') plans.value = plansRes.value.data || []
  loadingPlans.value = false
  if (adsRes.status === 'fulfilled') ads.value = adsRes.value.data || []
})

function fmt(v: number, cur = 'GBP') {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur || 'GBP', maximumFractionDigits: 0 }).format(v)
}

async function trackAdClick(ad: any) {
  await client.post('/advertisements/' + ad.id + '/click').catch(() => {})
  if (ad.listing?.slug) router.push('/listings/' + ad.listing.slug)
  else if (ad.link) window.open(ad.link, '_blank')
}
</script>

<style scoped>
.hero {
  color: #fff;
  background: radial-gradient(ellipse 50% 60% at 15% 20%, rgba(21,155,242,.18), transparent 70%), linear-gradient(160deg, #061423 0%, #0a2540 60%, #0b3556 100%);
}
/* Photo on the right (Kyrenia harbour, castle, villa and car), fading softly
   into the navy background on the left and at the bottom, where the search
   panel overlaps the hero. */
.hero::before {
  content: ''; position: absolute; top: 0; right: 0; bottom: 8.5rem; width: 70%;
  background: url('/hero-kyrenia.jpg') 70% 100% / cover no-repeat;
  -webkit-mask-image: linear-gradient(90deg, transparent 0%, rgba(0,0,0,.6) 22%, #000 45%), linear-gradient(180deg, #000 88%, transparent 100%);
  -webkit-mask-composite: source-in; mask-image: linear-gradient(90deg, transparent 0%, rgba(0,0,0,.6) 22%, #000 45%), linear-gradient(180deg, #000 88%, transparent 100%);
  mask-composite: intersect; pointer-events: none;
}
.hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(6,20,35,.55) 0%, rgba(6,20,35,0) 55%); pointer-events: none; }
@media (max-width: 767px) {
  .hero::before { width: 100%; bottom: 40%; opacity: .55; -webkit-mask-image: linear-gradient(180deg, #000 50%, transparent 100%); mask-image: linear-gradient(180deg, #000 50%, transparent 100%); }
  .hero::after { background: linear-gradient(180deg, rgba(6,20,35,.55), rgba(6,20,35,.75)); }
}
.hero-badge { background: rgba(18,197,232,.12); border: 1px solid rgba(18,197,232,.35); color: #bfefff }
.hero-title { color: #fff; letter-spacing: -.02em; text-shadow: 0 2px 24px rgba(0,0,0,.35) }
.hero-accent { background: linear-gradient(90deg, #4fd8f5, #12C5E8 50%, #4fe0b0); -webkit-background-clip: text; background-clip: text; color: transparent }
.hero-sub { color: #d3e2f1; max-width: 38rem }
</style>
