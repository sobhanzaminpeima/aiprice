<template>
  <MainLayout>
    <!-- Route to the right detail component based on listing type -->
    <component :is="detailComponent" v-if="!loading && detailComponent" />

    <div v-if="loading" class="min-h-screen flex items-center justify-center" role="status">
      <div class="text-center">
        <div class="w-12 h-12 rounded-full border-2 animate-spin mx-auto mb-4" style="border-color:rgba(var(--accent-teal-rgb),0.2);border-top-color:var(--accent-teal)"></div>
        <p style="color:var(--text-muted)">Loading…</p>
      </div>
    </div>

    <div v-else-if="notFound" class="max-w-xl mx-auto px-4 py-28 text-center">
      <p class="text-5xl mb-4">🔍</p>
      <h1 class="text-2xl font-black mb-3" style="color:var(--text-primary)">This listing is no longer available</h1>
      <p class="mb-8" style="color:var(--text-muted)">It may have been sold, rented or removed by the seller.</p>
      <div class="flex flex-wrap gap-3 justify-center">
        <RouterLink to="/cars" class="px-6 py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Browse cars</RouterLink>
        <RouterLink to="/properties" class="px-6 py-3 rounded-xl text-sm font-semibold" style="color:var(--text-primary);border:1px solid var(--border-strong)">Browse properties</RouterLink>
      </div>
    </div>

    <GenericDetailView v-else-if="!loading && listingType === 'other'" />
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import VehicleDetailView from '@/views/VehicleDetailView.vue'
import PropertyDetailView from '@/views/PropertyDetailView.vue'
import GenericDetailView from '@/views/GenericDetailView.vue'
import client from '@/api/client'
import { usePageMeta } from '@/composables/usePageMeta'

const route = useRoute()
const loading = ref(true)
const notFound = ref(false)
const listingType = ref<string | null>(null)
const listing = ref<any>(null)

const detailComponent = computed(() => {
  if (listingType.value === 'vehicle') return VehicleDetailView
  if (listingType.value === 'property') return PropertyDetailView
  return null
})

function describe(l: any): string {
  const s = l.listable ?? {}
  const facts = l.type === 'vehicle'
    ? [s.year, s.mileage ? `${Number(s.mileage).toLocaleString()} km` : null, s.fuel_type, s.transmission]
    : [s.bedrooms ? `${s.bedrooms} bed` : null, s.bathrooms ? `${s.bathrooms} bath` : null, s.area_sqm ? `${s.area_sqm} m²` : null]
  const price = `${l.currency} ${Number(l.price).toLocaleString()}`
  const verdict = l.ai_price_status ? ` AI price check: ${String(l.ai_price_status).replace(/_/g, ' ')}.` : ''
  return `${price} · ${[...facts.filter(Boolean), l.city].filter(Boolean).join(' · ')}.${verdict}`.slice(0, 160)
}

usePageMeta(() => {
  if (notFound.value) return { title: 'Listing not available', noindex: true }
  const l = listing.value
  if (!l) return null
  const image = l.thumbnail || l.images?.[0] || null
  return {
    title: l.title,
    description: describe(l),
    image,
    type: 'product',
    canonical: `/listings/${l.slug}`,
    // Rich result: price and availability can show directly in search results.
    jsonLd: {
      '@context': 'https://schema.org',
      '@type': l.type === 'vehicle' ? 'Car' : 'Product',
      name: l.title,
      description: (l.description || describe(l)).slice(0, 500),
      image: image ? [image] : undefined,
      sku: l.listing_number,
      ...(l.type === 'vehicle' && l.listable ? {
        vehicleModelDate: l.listable.year ? String(l.listable.year) : undefined,
        mileageFromOdometer: l.listable.mileage ? { '@type': 'QuantitativeValue', value: l.listable.mileage, unitCode: 'KMT' } : undefined,
        fuelType: l.listable.fuel_type || undefined,
      } : {}),
      offers: {
        '@type': 'Offer',
        price: Number(l.price),
        priceCurrency: l.currency,
        availability: 'https://schema.org/InStock',
        url: `${window.location.origin}/listings/${l.slug}`,
        areaServed: l.city || 'North Cyprus',
      },
    },
  }
})

async function detectType() {
  loading.value = true
  notFound.value = false
  try {
    const { data } = await client.get(`/listings/${route.params.slug}`)
    listing.value = data.listing ?? data
    listingType.value = listing.value.type ?? 'other'
  } catch (e: any) {
    listing.value = null
    listingType.value = null
    if (e?.response?.status === 404) notFound.value = true
    else listingType.value = 'other'
  } finally {
    loading.value = false
  }
}

onMounted(detectType)
watch(() => route.params.slug, detectType)
</script>
