<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="mb-8">
        <div class="ai-badge mb-3 flex items-center gap-1.5"><AppIcon name="home" :size="14" /> North Cyprus Real Estate</div>
        <h1 class="text-4xl font-black mb-2" style="color:var(--text-primary)">Properties <span class="gradient-text-violet">Marketplace</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">{{ total }} verified properties with AI investment scoring</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs font-semibold mr-1" style="color:var(--text-muted)">What are you looking for?</span>
        <button v-for="m in rankingModes" :key="m.value" @click="setMode(m.value)"
          class="px-4 py-1.5 rounded-full text-xs font-bold transition"
          :style="mode === m.value
            ? 'background:linear-gradient(135deg,#56d8ff,#a78bfa);color:var(--on-accent)'
            : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
          {{ m.label }}
        </button>
      </div>
      <div class="rounded-2xl p-4 mb-8 flex flex-wrap items-center gap-3" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
        <input v-model="filters.search" type="text" placeholder="Search by title or location..." class="input-dark flex-1 min-w-[160px]" @input="debouncedFetch" />
        <select v-model="filters.city" aria-label="City" class="input-dark w-36" @change="doFetch()">
          <option value="">All Cities</option>
          <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
        </select>
        <select v-model="filters.beds" aria-label="Bedrooms" class="input-dark w-28" @change="doFetch()">
          <option value="">Bedrooms</option>
          <option value="1">1+</option><option value="2">2+</option><option value="3">3+</option><option value="4">4+</option>
        </select>
        <select v-model="filters.sort" aria-label="Sort by" class="input-dark w-40" @change="doFetch()">
          <option value="newest">Newest First</option>
          <option value="price_asc">Price: Low → High</option>
          <option value="price_desc">Price: High → Low</option>
        </select>
        <button @click="reset" class="px-4 py-2.5 rounded-xl text-sm font-medium" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted)">Reset</button>
      </div>
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div v-for="i in 8" :key="i" class="rounded-2xl h-72 animate-pulse" style="background:var(--bg-card)"></div>
      </div>
      <div v-else-if="listings.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <ListingCard v-for="l in listings" :key="l.id" :listing="l" />
      </div>
      <div v-else class="text-center py-24">
        <AppIcon name="home" :size="48" class="mx-auto mb-4" />
        <template v-if="hasFilters">
          <p class="font-bold text-lg mb-2" style="color:var(--text-primary)">No properties match these filters</p>
          <p class="text-sm" style="color:var(--text-muted)">Try widening your search.</p>
        </template>
        <template v-else>
          <p class="font-bold text-lg mb-2" style="color:var(--text-primary)">No properties listed yet</p>
          <p class="text-sm mb-5" style="color:var(--text-muted)">Be the first — every listing gets an instant AI price check.</p>
          <RouterLink :to="sellLink" class="inline-block px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">List your property free</RouterLink>
        </template>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-10">
        <button v-for="p in pages" :key="p" @click="goPage(p)" class="w-9 h-9 rounded-xl text-sm font-semibold transition" :style="p === currentPage ? 'background:linear-gradient(135deg,#56d8ff,#a78bfa);color:var(--on-accent)' : typeof p === 'string' ? 'cursor:default;color:var(--text-muted)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">{{ p }}</button>
      </div>
    </div>
  </MainLayout>
</template>
<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore as useSellAuth } from '@/stores/auth'
import { usePageMeta } from '@/composables/usePageMeta'
usePageMeta(() => ({ title: 'Property for sale in North Cyprus', description: 'Apartments, villas and land for sale in North Cyprus with AI price verdicts against comparable listings.' }))
import { ref, computed, onMounted } from "vue"
import MainLayout from "@/layouts/MainLayout.vue"
import ListingCard from "@/components/listings/ListingCard.vue"
import client from "@/api/client"
import type { Listing } from "@/types"
const listings = ref<Listing[]>([]); const loading = ref(true); const total = ref(0); const currentPage = ref(1); const lastPage = ref(1)
let debounceTimer: ReturnType<typeof setTimeout>
const cities = ["Girne","Lefkosa","Gazimağusa","Iskele","Guzelyurt"]
const filters = ref({ search: "", city: "", beds: "", sort: "newest" })
const sellAuth = useSellAuth()
const sellLink = computed(() => (sellAuth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { redirect: '/dashboard/listings/new' } }))
const hasFilters = computed(() => !!(filters.value.search || filters.value.city || filters.value.beds))
const rankingModes = [
  { value: "", label: "Browse All" },
  { value: "find", label: "FIND — best fit for me" },
  { value: "deal", label: "DEAL — lowest price" },
  { value: "invest", label: "INVEST — best rental/growth potential" },
]
const mode = ref("")
function setMode(m: string) { mode.value = m; doFetch() }
const pages = computed(() => { const p: (number|string)[] = []; for (let i = 1; i <= lastPage.value; i++) { if (i===1||i===lastPage.value||Math.abs(i-currentPage.value)<=2) p.push(i); else if (p[p.length-1]!=="...") p.push("...") }; return p })
function debouncedFetch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(doFetch, 400) }
async function doFetch(page = 1) {
  loading.value = true; currentPage.value = page
  try {
    const params: any = { type: "property", page, per_page: 12 }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.city) params.city = filters.value.city
    if (filters.value.beds) params.min_bedrooms = filters.value.beds
    if (mode.value) { params.mode = mode.value }
    else if (filters.value.sort === "price_asc") { params.sort = "price_asc" }
    else if (filters.value.sort === "price_desc") { params.sort = "price_desc" }
    const { data } = await client.get("/listings", { params })
    listings.value = data.data ?? []; total.value = data.total ?? 0; lastPage.value = data.last_page ?? 1
  } catch { listings.value = [] } finally { loading.value = false }
}
function goPage(p: number|string) { if (typeof p === "number") doFetch(p) }
function reset() { filters.value = { search: "", city: "", beds: "", sort: "newest" }; mode.value = ""; doFetch() }
onMounted(doFetch)
</script>
