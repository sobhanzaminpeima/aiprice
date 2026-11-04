<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

      <!-- Page header -->
      <div class="mb-8">
        <div class="ai-badge mb-3 flex items-center gap-1.5"><AppIcon name="car" :size="14" /> North Cyprus Auto Market</div>
        <h1 class="text-4xl font-black mb-2" style="color:var(--text-primary)">Cars <span class="gradient-text">Marketplace</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">{{ total }} listings with AI price estimates</p>
      </div>

      <!-- Browse by category: body type and quick filters (URL driven, shareable) -->
      <nav aria-label="Car categories" class="mb-5">
        <div class="flex gap-2 overflow-x-auto pb-1 cat-row">
          <RouterLink v-for="b in bodyTypes" :key="b" :to="{ query: { ...route.query, body: filters.body === b ? undefined : b } }"
            class="cat-chip" :class="{ 'cat-chip--on': filters.body === b }" :aria-current="filters.body === b ? 'true' : undefined">{{ b }}</RouterLink>
        </div>
        <div class="flex gap-2 overflow-x-auto pt-2 pb-1 cat-row">
          <RouterLink v-for="q in quickFilters" :key="q.label" :to="{ query: { ...route.query, ...q.query } }" class="cat-chip cat-chip--soft">{{ q.label }}</RouterLink>
        </div>
      </nav>
      <div v-if="activeChips.length" class="flex flex-wrap gap-2 mb-4" aria-label="Active filters">
        <RouterLink v-for="c in activeChips" :key="c.key" :to="{ query: { ...route.query, [c.key]: undefined } }" class="cat-chip cat-chip--on">{{ c.label }} ✕</RouterLink>
      </div>

      <!-- Ranking mode: FIND / DEAL / INVEST -->
      <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs font-semibold mr-1" style="color:var(--text-muted)">What are you looking for?</span>
        <button v-for="m in rankingModes" :key="m.value" @click="setMode(m.value)"
          class="px-4 py-1.5 rounded-full text-xs font-bold transition"
          :style="mode === m.value
            ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)'
            : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
          {{ m.label }}
        </button>
      </div>

      <!-- Filter bar -->
      <div class="rounded-2xl p-4 mb-8 flex flex-wrap items-center gap-3" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
        <input v-model="filters.search" type="text" placeholder="Search make, model..." class="input-dark flex-1 min-w-[160px]" @input="debouncedFetch" />
        <select v-model="filters.city" aria-label="City" class="input-dark w-36" @change="syncUrl">
          <option value="">All Cities</option>
          <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
        </select>
        <select v-model="filters.fuel" aria-label="Fuel type" class="input-dark w-32" @change="syncUrl">
          <option value="">Fuel Type</option>
          <option value="petrol">Petrol</option>
          <option value="diesel">Diesel</option>
          <option value="hybrid">Hybrid</option>
          <option value="electric">Electric</option>
        </select>
        <select v-model="filters.transmission" aria-label="Transmission" class="input-dark w-32" @change="syncUrl">
          <option value="">Transmission</option>
          <option value="automatic">Automatic</option>
          <option value="manual">Manual</option>
        </select>
        <select v-model="filters.sort" aria-label="Sort by" class="input-dark w-40" @change="syncUrl">
          <option value="newest">Newest First</option>
          <option value="price_asc">Price: Low → High</option>
          <option value="price_desc">Price: High → Low</option>
          <option value="trust">AI Trust Score</option>
        </select>
        <button @click="resetFilters" class="px-4 py-2.5 rounded-xl text-sm font-medium transition" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted)">Reset</button>
      </div>

      <!-- Listings grid -->
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="i in 8" :key="i" class="rounded-2xl h-72 animate-pulse" style="background:var(--bg-card)"></div>
      </div>

      <div v-else-if="listings.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <ListingCard v-for="l in listings" :key="l.id" :listing="l" />
      </div>

      <div v-else class="text-center py-24">
        <AppIcon name="search" :size="48" class="mx-auto mb-4" />
        <template v-if="hasFilters">
          <p class="font-bold text-lg mb-2" style="color:var(--text-primary)">No cars match these filters</p>
          <p class="text-sm" style="color:var(--text-muted)">Try widening your search.</p>
        </template>
        <template v-else>
          <p class="font-bold text-lg mb-2" style="color:var(--text-primary)">No cars listed yet</p>
          <p class="text-sm mb-5" style="color:var(--text-muted)">Be the first — every listing gets an instant AI price check.</p>
          <RouterLink :to="sellLink" class="inline-block px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">List your car free</RouterLink>
        </template>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="flex items-center justify-center gap-2 mt-10">
        <button v-for="p in pages" :key="p" @click="goPage(p)" :disabled="p === '...'" class="w-9 h-9 rounded-xl text-sm font-semibold transition" :style="p === currentPage ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)' : p === '...' ? 'cursor:default;color:var(--text-muted);background:transparent' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">
          {{ p }}
        </button>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore as useSellAuth } from '@/stores/auth'
import { usePageMeta } from '@/composables/usePageMeta'
usePageMeta(() => ({ title: 'Cars for sale in North Cyprus', description: 'Used and new cars for sale in Girne, Lefkoşa, Gazimağusa and across North Cyprus — each with an AI fair-price check.' }))
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ListingCard from '@/components/listings/ListingCard.vue'
import client from '@/api/client'
import type { Listing } from '@/types'

const listings = ref<Listing[]>([])
const loading = ref(true)
const total = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)
let debounceTimer: ReturnType<typeof setTimeout>

const cities = ['Girne', 'Lefkosa', 'Gazimağusa', 'Iskele', 'Guzelyurt']
const blankFilters = () => ({ search: '', city: '', fuel: '', transmission: '', sort: 'newest', brand: '', body: '', min_year: '', max_price: '', max_mileage: '' })
const filters = ref(blankFilters())
const route = useRoute()
const router = useRouter()
const bodyTypes = ['Sedan', 'SUV', 'Hatchback', 'Crossover', 'Pickup', 'Coupe', 'Convertible', 'Van']
const quickFilters = [
  { label: 'Automatic', query: { transmission: 'automatic' } },
  { label: 'Electric', query: { fuel: 'electric' } },
  { label: 'Hybrid', query: { fuel: 'hybrid' } },
  { label: '2020 or newer', query: { min_year: '2020' } },
  { label: 'Under 50,000 km', query: { max_mileage: '50000' } },
  { label: 'Under £10,000', query: { max_price: '10000' } },
  { label: 'Under £20,000', query: { max_price: '20000' } },
]
const activeChips = computed(() => {
  const f = filters.value
  const chips: { key: string; label: string }[] = []
  if (f.brand) chips.push({ key: 'brand', label: f.brand })
  if (f.body) chips.push({ key: 'body', label: f.body })
  if (f.min_year) chips.push({ key: 'min_year', label: `${f.min_year}+` })
  if (f.max_price) chips.push({ key: 'max_price', label: `≤ £${Number(f.max_price).toLocaleString('en-GB')}` })
  if (f.max_mileage) chips.push({ key: 'max_mileage', label: `≤ ${Number(f.max_mileage).toLocaleString('en-GB')} km` })
  return chips
})
// The URL is the source of truth, so menu links like /cars?brand=Toyota work.
function readQuery() {
  const q = route.query
  const str = (k: string) => (typeof q[k] === 'string' ? (q[k] as string) : '')
  filters.value = { ...blankFilters(), search: str('search'), city: str('city'), fuel: str('fuel').toLowerCase(), transmission: str('transmission').toLowerCase(),
    sort: str('sort') || 'newest', brand: str('brand'), body: str('body'), min_year: str('min_year'), max_price: str('max_price'), max_mileage: str('max_mileage') }
}
const sellAuth = useSellAuth()
const sellLink = computed(() => (sellAuth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { redirect: '/dashboard/listings/new' } }))
const hasFilters = computed(() => !!(filters.value.search || filters.value.city || filters.value.fuel || filters.value.transmission || activeChips.value.length))

const rankingModes = [
  { value: '', label: 'Browse All' },
  { value: 'find', label: 'FIND — best fit for me' },
  { value: 'deal', label: 'DEAL — lowest price' },
  { value: 'invest', label: 'INVEST — best resale/investment' },
]
const mode = ref('')
function setMode(m: string) { mode.value = m; fetchListings() }

const pages = computed(() => {
  const p: (number | string)[] = []
  for (let i = 1; i <= lastPage.value; i++) {
    if (i === 1 || i === lastPage.value || Math.abs(i - currentPage.value) <= 2) p.push(i)
    else if (p[p.length - 1] !== '...') p.push('...')
  }
  return p
})

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => syncUrl(), 400)
}

async function fetchListings(page = 1) {
  loading.value = true; currentPage.value = page
  try {
    const params: any = { type: 'vehicle', page, per_page: 12 }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.city) params.city = filters.value.city
    if (filters.value.fuel) params.fuel_type = filters.value.fuel
    if (filters.value.transmission) params.transmission = filters.value.transmission
    if (filters.value.brand) params.brand = filters.value.brand
    if (filters.value.body) params.body = filters.value.body
    if (filters.value.min_year) params.min_year = filters.value.min_year
    if (filters.value.max_price) params.max_price = filters.value.max_price
    if (filters.value.max_mileage) params.max_mileage = filters.value.max_mileage
    if (mode.value) {
      // Ranking mode overrides plain sort on the backend (see RankingEngineService)
      params.mode = mode.value
    } else if (filters.value.sort === 'price_asc') { params.sort = 'price_asc' }
    else if (filters.value.sort === 'price_desc') { params.sort = 'price_desc' }
    else if (filters.value.sort === 'trust') { params.sort = 'trust_score' }
    const { data } = await client.get('/listings', { params })
    listings.value = data.data ?? []
    total.value = data.total ?? 0
    lastPage.value = data.last_page ?? 1
  } catch { listings.value = [] } finally { loading.value = false }
}

// Push dropdown/search changes into the URL; the query watcher then fetches.
function syncUrl() {
  const f = filters.value
  const query: Record<string, string> = {}
  for (const [k, v] of Object.entries(f)) if (v && !(k === 'sort' && v === 'newest')) query[k] = String(v)
  router.replace({ query })
}

function goPage(p: number | string) { if (typeof p === 'number') fetchListings(p) }
function resetFilters() { mode.value = ''; filters.value = blankFilters(); if (Object.keys(route.query).length) router.push({ query: {} }); else fetchListings() }

onMounted(() => { readQuery(); fetchListings() })
watch(() => route.query, () => { readQuery(); fetchListings() })
</script>
<style scoped>
.cat-row { scrollbar-width: none }
.cat-chip { white-space:nowrap;padding:7px 14px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid var(--border-strong);color:var(--text-secondary);background:var(--bg-card) }
.cat-chip:hover { border-color:var(--accent-cyan);color:var(--accent-cyan) }
.cat-chip--soft { background:var(--surface-overlay);border-color:var(--border-subtle) }
.cat-chip--on { background:var(--accent-cyan);border-color:var(--accent-cyan);color:var(--on-accent) }
</style>
