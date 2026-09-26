<template>
  <div>
    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-8">
      <input
        v-model="filters.search"
        @input="debouncedFetch"
        placeholder="Search..."
        class="bg-[var(--bg-surface)] border border-[var(--border-strong)] text-[var(--text-primary)] rounded-xl px-4 py-2 text-sm outline-none focus:border-[var(--accent-teal)] transition w-64"
      />
      <input
        v-model="filters.city"
        @input="debouncedFetch"
        placeholder="City..."
        class="bg-[var(--bg-surface)] border border-[var(--border-strong)] text-[var(--text-primary)] rounded-xl px-4 py-2 text-sm outline-none focus:border-[var(--accent-teal)] transition w-40"
      />
      <input v-model="filters.min_price" @input="debouncedFetch" type="number" placeholder="Min price"
        class="bg-[var(--bg-surface)] border border-[var(--border-strong)] text-[var(--text-primary)] rounded-xl px-4 py-2 text-sm outline-none w-32" />
      <input v-model="filters.max_price" @input="debouncedFetch" type="number" placeholder="Max price"
        class="bg-[var(--bg-surface)] border border-[var(--border-strong)] text-[var(--text-primary)] rounded-xl px-4 py-2 text-sm outline-none w-32" />
      <select v-model="filters.sort" aria-label="Sort by" @change="fetchListings" class="bg-[var(--bg-surface)] border border-[var(--border-strong)] text-[var(--text-primary)] rounded-xl px-4 py-2 text-sm outline-none">
        <option value="newest">Newest First</option>
        <option value="price_asc">Price: Low to High</option>
        <option value="price_desc">Price: High to Low</option>
        <option value="most_viewed">Most Viewed</option>
        <option value="trust_score">Highest Trust</option>
      </select>
    </div>

    <!-- Results count -->
    <div class="flex items-center justify-between mb-6">
      <p class="text-[var(--text-muted)] text-sm">
        {{ listingsStore.pagination.total }} listings found
      </p>
    </div>

    <!-- Grid -->
    <div v-if="listingsStore.loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <div v-for="i in 8" :key="i" class="bg-[var(--bg-surface)] rounded-2xl h-80 animate-pulse" />
    </div>

    <div v-else-if="listingsStore.listings.length === 0" class="text-center py-20 text-[var(--text-muted)]">
      <AppIcon name="search" :size="48" class="mx-auto mb-4" />
      <p class="text-xl font-semibold mb-2">No listings found</p>
      <p class="text-sm">Try adjusting your search filters.</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <ListingCard v-for="listing in listingsStore.listings" :key="listing.id" :listing="listing" />
    </div>

    <!-- Pagination -->
    <div v-if="listingsStore.pagination.last_page > 1" class="flex items-center justify-center gap-2 mt-12">
      <button
        v-for="p in pageNumbers"
        :key="p"
        @click="goToPage(p)"
        class="w-10 h-10 rounded-lg text-sm font-medium transition"
        :class="p === listingsStore.pagination.current_page ? 'bg-[#6fffd2] text-[var(--on-accent)]' : 'bg-[var(--bg-surface)] text-[var(--text-muted)] hover:text-[var(--text-primary)] border border-[var(--border-subtle)]'"
      >
        {{ p }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import { ref, computed, onMounted, watch } from 'vue'
import ListingCard from './ListingCard.vue'
import { useListingsStore } from '@/stores/listings'

const props = defineProps<{
  type?: string
  initialSearch?: string
  initialType?: string
  initialCity?: string
  initialMode?: string
  initialBudgetMax?: string
}>()

const listingsStore = useListingsStore()

const filters = ref({
  search: props.initialSearch || '',
  city: props.initialCity || '',
  min_price: '',
  max_price: props.initialBudgetMax || '',
  sort: 'newest',
  type: props.type || props.initialType || '',
  mode: props.initialMode || '',
  page: 1,
})

let debounceTimer: any

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchListings, 400)
}

async function fetchListings() {
  const params: Record<string, any> = { page: filters.value.page }
  if (filters.value.mode) {
    // Ranking mode (FIND/DEAL/INVEST) overrides plain sort on the backend.
    params.mode = filters.value.mode
  } else {
    params.sort = filters.value.sort
  }
  if (filters.value.type) params.type = filters.value.type
  if (filters.value.search) params.search = filters.value.search
  if (filters.value.city) params.city = filters.value.city
  if (filters.value.min_price) params.min_price = filters.value.min_price
  if (filters.value.max_price) params.max_price = filters.value.max_price
  await listingsStore.fetchListings(params)
}

function goToPage(p: number) {
  filters.value.page = p
  fetchListings()
}

const pageNumbers = computed(() => {
  const total = listingsStore.pagination.last_page
  const current = listingsStore.pagination.current_page
  const pages = []
  for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) {
    pages.push(i)
  }
  return pages
})

watch(() => props.initialSearch, (v) => { filters.value.search = v || ''; fetchListings() })
watch(() => props.initialType, (v) => { filters.value.type = v || ''; fetchListings() })
watch(() => props.initialCity, (v) => { filters.value.city = v || ''; fetchListings() })
watch(() => props.initialMode, (v) => { filters.value.mode = v || ''; fetchListings() })
watch(() => props.initialBudgetMax, (v) => { filters.value.max_price = v || ''; fetchListings() })
onMounted(fetchListings)
</script>
