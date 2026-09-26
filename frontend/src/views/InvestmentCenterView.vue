<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-24">
      <div class="text-center mb-12">
        <span class="ai-badge mb-4 inline-block">Investment Center</span>
        <h1 class="text-4xl md:text-5xl font-black mb-4" style="color:var(--text-primary)">Top <span class="gradient-text">investment</span> picks</h1>
        <p class="text-lg max-w-2xl mx-auto" style="color:var(--text-muted)">Active listings ranked by AI investment score — how the price compares to the market, how much demand the listing has, and how trustworthy it is.</p>
      </div>

      <div class="flex justify-center gap-2 mb-8" role="tablist">
        <button v-for="t in tabs" :key="t.key" role="tab" :aria-selected="type === t.key" @click="setType(t.key)"
          class="px-5 py-2 rounded-xl text-sm font-semibold"
          :style="type === t.key ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.25)' : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid transparent'">
          {{ t.label }}
        </button>
      </div>

      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="i in 6" :key="i" class="h-80 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <p v-else-if="error" class="text-center text-sm" style="color:#ef4444">{{ error }}</p>
      <p v-else-if="!listings.length" class="text-center py-16" style="color:var(--text-muted)">No scored {{ type === 'property' ? 'properties' : 'vehicles' }} yet — check back soon.</p>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        <ListingCard v-for="l in listings" :key="l.id" :listing="l" />
      </div>

      <div class="rounded-2xl p-6 text-sm max-w-3xl mx-auto" style="background:var(--bg-surface);border:1px solid var(--border-subtle);color:var(--text-muted)">
        <strong style="color:var(--text-secondary)">How to read this:</strong> scores are computed from listings on AIPrice.app, not guaranteed returns. Always view in person, check title deeds (for property) and service history (for cars), and never pay a deposit before a viewing.
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import MainLayout from '@/layouts/MainLayout.vue'
import ListingCard from '@/components/listings/ListingCard.vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { usePageMeta } from '@/composables/usePageMeta'
import type { Listing } from '@/types'

const tabs = [
  { key: 'property', label: 'Property' },
  { key: 'vehicle', label: 'Cars' },
]
const type = ref('property')
const listings = ref<Listing[]>([])
const loading = ref(true)
const error = ref('')

usePageMeta(() => ({
  title: 'Investment picks',
  description: 'North Cyprus property and cars ranked by AI investment score — price against market, demand and trust.',
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const r = await client.get('/listings', { params: { type: type.value, mode: 'invest', per_page: 9 } })
    listings.value = r.data.data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load listings.')
  } finally {
    loading.value = false
  }
}

function setType(t: string) { type.value = t; load() }

onMounted(load)
</script>
