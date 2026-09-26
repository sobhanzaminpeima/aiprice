<template>
  <MainLayout>
    <!-- HERO -->
    <section class="relative overflow-hidden pt-20 pb-24 px-4">
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[900px] h-[500px] pointer-events-none" style="background:radial-gradient(ellipse 70% 60% at 50% 0%,rgba(111,255,210,0.08) 0%,transparent 70%)"></div>
      <div class="max-w-7xl mx-auto relative z-10">
        <div class="text-center mb-12">
          <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold mb-6" style="background:rgba(111,255,210,0.08);border:1px solid rgba(111,255,210,0.2);color:var(--accent-teal)">
            <span class="w-1.5 h-1.5 rounded-full" style="background:#6fffd2;animation:pulse-glow 2s infinite"></span>
            AI-Powered Marketplace · North Cyprus
          </div>
          <h1 class="text-4xl sm:text-6xl font-black leading-tight sm:leading-none mb-6" style="color:var(--text-primary)">
            Find the Best <span class="gradient-text">Priced</span><br/>Cars &amp; Properties
          </h1>
          <p class="text-lg max-w-2xl mx-auto mb-10" style="color:var(--text-muted)">
            AI-verified prices on every car and property listing. Know the real market value before you buy or sell.
          </p>

          <!-- Welcome back — AI Preference Graph memory. Only for signed-in
               users with a real prior search on record. -->
          <div v-if="lastPreference" class="max-w-lg mx-auto mb-6 rounded-2xl p-4 flex items-center justify-between gap-3"
            style="background:rgba(111,255,210,0.06);border:1px solid rgba(111,255,210,0.2)">
            <div class="text-left">
              <p class="text-xs font-bold" style="color:var(--text-primary)">Welcome back — your last search</p>
              <p class="text-xs" style="color:var(--text-muted)">
                {{ lastPreference.asset_type }}<span v-if="lastPreference.budget_max"> under {{ Number(lastPreference.budget_max).toLocaleString() }}</span><span v-if="lastPreference.city"> in {{ lastPreference.city }}</span>
                <template v-if="newMatchesCount > 0"> · <strong style="color:var(--accent-teal)">{{ newMatchesCount }} new match{{ newMatchesCount === 1 ? '' : 'es' }}</strong></template>
              </p>
            </div>
            <button @click="rerunLastSearch" class="px-3 py-1.5 rounded-lg text-xs font-bold shrink-0" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">View →</button>
          </div>

          <!-- AI Intent Composer — replaces a plain search bar. Choose what you're
               after, what matters to you, and your budget; AI ranks results
               instead of you scrolling through hundreds of listings. -->
          <div class="max-w-2xl mx-auto rounded-3xl p-6" style="background:var(--bg-card-translucent);border:1px solid rgba(111,255,210,0.18)">
            <p class="text-sm font-bold mb-3" style="color:var(--text-primary)">I'm looking for</p>
            <div class="flex flex-wrap justify-center gap-2 mb-5">
              <button v-for="t in assetTypes" :key="t.value" @click="intent.type = t.value"
                class="px-4 py-2 rounded-xl text-sm font-bold transition inline-flex items-center gap-2"
                :style="intent.type === t.value
                  ? 'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)'
                  : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">
                <AssetIcon :type="t.value as any" :size="16" /> {{ t.label }}
              </button>
            </div>

            <p class="text-sm font-bold mb-3" style="color:var(--text-primary)">What's important to you?</p>
            <div class="flex flex-wrap justify-center gap-2 mb-5">
              <button v-for="p in priorityOptions" :key="p.value" @click="togglePriority(p.value)"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                :style="intent.priorities.includes(p.value)
                  ? 'background:rgba(111,255,210,0.16);border:1px solid rgba(111,255,210,0.5);color:var(--accent-teal)'
                  : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
                {{ p.label }}
              </button>
            </div>

            <p class="text-sm font-bold mb-3" style="color:var(--text-primary)">Budget</p>
            <div class="flex flex-wrap justify-center gap-2 mb-5">
              <button v-for="b in budgetOptions" :key="b.label" @click="intent.budgetMax = b.value"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                :style="intent.budgetMax === b.value
                  ? 'background:rgba(86,216,255,0.16);border:1px solid rgba(86,216,255,0.5);color:var(--accent-cyan)'
                  : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
                {{ b.label }}
              </button>
              <input v-if="intent.budgetMax === 'custom'" v-model="intent.budgetCustom" type="number" placeholder="Max amount"
                class="px-3 py-1.5 rounded-lg text-xs w-32 outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
              <select v-model="intent.city" aria-label="City" class="px-3 py-1.5 rounded-lg text-xs outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted)">
                <option value="">Any City</option>
                <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <button @click="findMyMatch" class="btn-primary w-full py-3 rounded-xl text-sm font-bold">
              ✦ Find My Best Match →
            </button>
          </div>
        </div>

        <!-- Stats bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-3xl mx-auto">
          <div v-for="s in stats" :key="s.label" class="text-center p-4 rounded-2xl" style="background:var(--bg-card-translucent);border:1px solid var(--border-subtle)">
            <p class="text-2xl font-black mb-1" :style="{color:s.color}">{{ s.value }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ s.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- AI MARKETPLACE MAP -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(86,216,255,0.1);border:1px solid rgba(86,216,255,0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#56d8ff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">AI Marketplace <span style="color:var(--accent-cyan)">Map</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">Every listing, live on the map of North Cyprus</p>
          </div>
        </div>
        <RouterLink to="/search?view=map" class="text-xs font-semibold" style="color:var(--accent-teal)">Open full map →</RouterLink>
      </div>
      <ListingsMap :height="420" compact />
    </section>

    <!-- TOP 10 SPONSORED LISTINGS (Advertisements) -->
    <section v-if="ads.length > 0" class="px-4 pb-16 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#fbbf24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">Top 10 <span style="color:#fbbf24">Sponsored</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">Premium promoted listings</p>
          </div>
        </div>
        <RouterLink to="/search?sponsored=1" class="text-xs font-semibold" style="color:var(--accent-teal)">View all →</RouterLink>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <div v-for="(ad, i) in ads.slice(0,10)" :key="ad.id"
          class="rounded-2xl overflow-hidden cursor-pointer transition-all hover:scale-[1.02]"
          style="background:var(--bg-surface);border:1px solid rgba(251,191,36,0.2)"
          @click="trackAdClick(ad)">
          <div class="relative aspect-[4/3] overflow-hidden" style="background:var(--surface-overlay-soft)">
            <img v-if="ad.listing?.thumbnail" :src="ad.listing.thumbnail" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#2a3a4a" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            </div>
            <div class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-xs font-bold" style="background:#fbbf24;color:var(--on-accent)">#{{ i+1 }}</div>
          </div>
          <div class="p-3">
            <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ ad.listing?.title || ad.name }}</p>
            <p class="text-sm font-black mt-1" style="color:#fbbf24">{{ ad.listing?.price ? fmt(ad.listing.price, ad.listing.currency) : 'Sponsored' }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- AI BEST PRICES — VEHICLES -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(111,255,210,0.1);border:1px solid rgba(111,255,210,0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">Best Price <span class="gradient-text">Cars</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">AI-verified best value vehicles</p>
          </div>
        </div>
        <RouterLink to="/cars" class="text-xs font-semibold" style="color:var(--accent-teal)">View all →</RouterLink>
      </div>
      <div v-if="loadingVehicles" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else-if="bestVehicles.length===0" class="text-center py-16 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p style="color:var(--text-muted)">No vehicle listings available yet.</p>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in bestVehicles" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- NEWEST VEHICLES -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(86,216,255,0.1);border:1px solid rgba(86,216,255,0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#56d8ff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">Newest <span style="color:var(--accent-cyan)">Vehicles</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">Latest car listings</p>
          </div>
        </div>
        <RouterLink to="/cars?sort=newest" class="text-xs font-semibold" style="color:var(--accent-teal)">View all →</RouterLink>
      </div>
      <div v-if="loadingVehicles" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in newestVehicles" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- AI BEST PRICES — PROPERTIES -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(167,139,250,0.1);border:1px solid rgba(167,139,250,0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">Best Price <span style="color:var(--accent-violet)">Properties</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">AI-verified best value real estate</p>
          </div>
        </div>
        <RouterLink to="/properties" class="text-xs font-semibold" style="color:var(--accent-teal)">View all →</RouterLink>
      </div>
      <div v-if="loadingProperties" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else-if="bestProperties.length===0" class="text-center py-16 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p style="color:var(--text-muted)">No property listings available yet.</p>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in bestProperties" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- NEWEST PROPERTIES -->
    <section class="px-4 pb-20 max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(86,216,255,0.1);border:1px solid rgba(86,216,255,0.2)">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#56d8ff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <h2 class="text-xl font-black" style="color:var(--text-primary)">Newest <span style="color:var(--accent-cyan)">Properties</span></h2>
            <p class="text-xs" style="color:var(--text-muted)">Latest real estate listings</p>
          </div>
        </div>
        <RouterLink to="/properties?sort=newest" class="text-xs font-semibold" style="color:var(--accent-teal)">View all →</RouterLink>
      </div>
      <div v-if="loadingProperties" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <ListingCard v-for="l in newestProperties" :key="l.id" :listing="l" />
      </div>
    </section>

    <!-- SUBSCRIPTION PLANS -->
    <section class="px-4 pb-24 max-w-7xl mx-auto">
      <div class="text-center mb-12">
        <h2 class="text-3xl font-black mb-3" style="color:var(--text-primary)">Start Selling <span class="gradient-text">Today</span></h2>
        <p class="text-base max-w-lg mx-auto" style="color:var(--text-muted)">Choose a plan to list your car or property. AI price analysis included on all paid plans.</p>
      </div>
      <div v-if="loadingPlans" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="(plan, i) in plans" :key="plan.id" class="rounded-2xl p-6 flex flex-col" :style="i===1?'background:var(--bg-surface);border:2px solid rgba(111,255,210,0.4)':'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
          <div v-if="i===1" class="text-center mb-3">
            <span class="text-xs font-bold px-3 py-1 rounded-full" style="background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.3)">Most Popular</span>
          </div>
          <h3 class="font-black text-lg mb-1" style="color:var(--text-primary)">{{ plan.name }}</h3>
          <p class="text-xs mb-4" style="color:var(--text-muted)">{{ plan.description || 'Perfect for individual sellers' }}</p>
          <div class="mb-4">
            <span class="text-3xl font-black" :style="{color: i===1?'#6fffd2':'var(--text-primary)'}">£{{ plan.price_monthly }}</span>
            <span class="text-sm" style="color:var(--text-muted)">/month</span>
          </div>
          <ul class="space-y-2 text-xs mb-6 flex-1" style="color:var(--text-secondary)">
            <li class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              {{ plan.max_listings }} listings
            </li>
            <li v-if="plan.ai_price_analysis" class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              AI price analysis
            </li>
            <li v-if="plan.featured_listing" class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              Featured placement
            </li>
            <li v-if="plan.priority_support" class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              Priority support
            </li>
          </ul>
          <RouterLink :to="auth.isAuthenticated ? '/dashboard' : '/register'" class="block text-center py-3 rounded-xl font-bold text-sm" :style="i===1?'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)':'background:var(--border-subtle);color:var(--text-secondary);border:1px solid var(--border-strong)'">
            {{ plan.price_monthly === 0 ? 'Get Started Free' : 'Choose ' + plan.name }}
          </RouterLink>
        </div>
      </div>
    </section>
  </MainLayout>
</template>
<script setup lang="ts">
import { usePageMeta } from '@/composables/usePageMeta'
usePageMeta(() => ({ title: undefined, description: 'Every car and property listing in North Cyprus checked against the market by AI. See the fair price before you buy or sell.' }))
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ListingCard from '@/components/listings/ListingCard.vue'
import ListingsMap from '@/components/listings/ListingsMap.vue'
import AssetIcon from '@/components/ui/AssetIcon.vue'
import { useAuthStore } from '@/stores/auth'
import client from '@/api/client'

const router = useRouter()
const auth = useAuthStore()

const lastPreference = ref<{ asset_type: string; budget_max: number | null; city: string | null } | null>(null)
const newMatchesCount = ref(0)

const cities = ['Girne', 'Lefkosa', 'Gazimağusa', 'Iskele', 'Guzelyurt', 'Lefke']

const assetTypes = [
  { value: 'vehicle', label: 'Car' },
  { value: 'property', label: 'Property' },
  { value: 'rental', label: 'Rental' },
]

// Each priority chip nudges the search toward one of the three ranking
// profiles the backend actually supports (RankingEngineService: find/deal/invest).
// This is a coarse mapping today — a per-chip weighted profile is a future
// enhancement once the ranking engine supports custom weight vectors.
const priorityOptions = [
  { value: 'best_price', label: 'Best Price', mode: 'deal' },
  { value: 'best_deal', label: 'Best Deal', mode: 'deal' },
  { value: 'low_cost', label: 'Low Monthly Cost', mode: 'deal' },
  { value: 'family', label: 'Family', mode: 'find' },
  { value: 'luxury', label: 'Luxury', mode: 'find' },
  { value: 'low_risk', label: 'Low Risk', mode: 'find' },
  { value: 'near_me', label: 'Near Me', mode: 'find' },
  { value: 'investment', label: 'Investment', mode: 'invest' },
  { value: 'high_resale', label: 'High Resale', mode: 'invest' },
]

const budgetOptions = [
  { label: 'Under £10K', value: '10000' },
  { label: '£10K–20K', value: '20000' },
  { label: '£20K–30K', value: '30000' },
  { label: 'Custom', value: 'custom' },
]

const intent = ref({
  type: 'vehicle',
  priorities: [] as string[],
  budgetMax: '',
  budgetCustom: '',
  city: '',
})

function togglePriority(value: string) {
  const i = intent.value.priorities.indexOf(value)
  if (i === -1) intent.value.priorities.push(value)
  else intent.value.priorities.splice(i, 1)
}

function resolveMode(): string {
  if (!intent.value.priorities.length) return ''
  const counts: Record<'find' | 'deal' | 'invest', number> = { find: 0, deal: 0, invest: 0 }
  for (const p of intent.value.priorities) {
    const opt = priorityOptions.find(o => o.value === p)
    if (opt) counts[opt.mode as 'find' | 'deal' | 'invest']++
  }
  const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1])
  return sorted[0]?.[0] ?? 'find'
}

async function findMyMatch() {
  const params = new URLSearchParams()
  params.set('type', intent.value.type)
  const mode = resolveMode()
  if (mode) params.set('mode', mode)
  const budget = intent.value.budgetMax === 'custom' ? intent.value.budgetCustom : intent.value.budgetMax
  if (budget) params.set('budget_max', String(budget))
  if (intent.value.city) params.set('city', intent.value.city)

  // AI Preference Graph — remember this search for signed-in users so the
  // homepage can say "welcome back" next visit. Best-effort: never blocks navigation.
  if (auth.isAuthenticated) {
    client.post('/me/search-preferences', {
      asset_type: intent.value.type,
      budget_max: budget || null,
      city: intent.value.city || null,
      priorities: intent.value.priorities,
    }).catch(() => {})
  }

  router.push('/search?' + params.toString())
}

function rerunLastSearch() {
  if (!lastPreference.value) return
  const params = new URLSearchParams()
  params.set('type', lastPreference.value.asset_type)
  if (lastPreference.value.budget_max) params.set('budget_max', String(lastPreference.value.budget_max))
  if (lastPreference.value.city) params.set('city', lastPreference.value.city)
  router.push('/search?' + params.toString())
}

const bestVehicles = ref<any[]>([])
const newestVehicles = ref<any[]>([])
const bestProperties = ref<any[]>([])
const newestProperties = ref<any[]>([])
const plans = ref<any[]>([])
const ads = ref<any[]>([])
const loadingVehicles = ref(true)
const loadingProperties = ref(true)
const loadingPlans = ref(true)

const stats = ref([
  { label: 'Active Listings', value: '...', color: '#6fffd2' },
  { label: 'Verified Cars', value: '...', color: '#56d8ff' },
  { label: 'Properties', value: '...', color: '#a78bfa' },
  { label: 'AI Analyses', value: '...', color: '#fbbf24' },
])

onMounted(async () => {
  if (auth.isAuthenticated) {
    client.get('/me/search-preferences/last').then(({ data }) => {
      if (data.preference) {
        lastPreference.value = data.preference
        newMatchesCount.value = data.new_matches ?? 0
      }
    }).catch(() => {})
  }

  const [statsRes, vehiclesRes, propsRes, plansRes, adsRes] = await Promise.allSettled([
    client.get('/market/stats').catch(() => null),
    client.get('/listings?type=vehicle&per_page=8&sort=price_asc'),
    client.get('/listings?type=property&per_page=8&sort=price_asc'),
    client.get('/subscription-plans'),
    client.get('/advertisements?slot=top10'),
  ])

  if (statsRes.status === 'fulfilled' && statsRes.value) {
    const s = statsRes.value.data
    stats.value = [
      { label: 'Active Listings', value: (s.active_listings || 0).toLocaleString(), color: '#6fffd2' },
      { label: 'Verified Cars', value: (s.vehicle_listings || 0).toLocaleString(), color: '#56d8ff' },
      { label: 'Properties', value: (s.property_listings || 0).toLocaleString(), color: '#a78bfa' },
      { label: 'AI Analyses', value: (s.ai_analyses_today || 0).toLocaleString(), color: '#fbbf24' },
    ]
  }

  if (vehiclesRes.status === 'fulfilled') {
    const data = vehiclesRes.value.data?.data || vehiclesRes.value.data || []
    bestVehicles.value = data.slice(0, 4)
    newestVehicles.value = data.slice(4, 8)
  }
  loadingVehicles.value = false

  if (propsRes.status === 'fulfilled') {
    const data = propsRes.value.data?.data || propsRes.value.data || []
    bestProperties.value = data.slice(0, 4)
    newestProperties.value = data.slice(4, 8)
  }
  loadingProperties.value = false

  if (plansRes.status === 'fulfilled') {
    plans.value = plansRes.value.data || []
  }
  loadingPlans.value = false

  if (adsRes.status === 'fulfilled') {
    ads.value = adsRes.value.data || []
  }
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