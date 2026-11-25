<template>
  <div v-if="loading" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <div class="w-12 h-12 rounded-full border-2 animate-spin mx-auto mb-4" style="border-color:rgba(var(--accent-teal-rgb),0.2);border-top-color:var(--accent-teal)"></div>
      <p style="color:var(--text-muted)">{{ $t('Loading property...') }}</p>
    </div>
  </div>

  <div v-else-if="!listing" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <AppIcon name="home" :size="64" class="mx-auto mb-4" />
      <h2 class="text-xl font-bold mb-2" style="color:var(--text-primary)">{{ $t('Property Not Found') }}</h2>
      <RouterLink to="/properties" class="btn-primary mt-4 inline-block">{{ $t('Browse Properties') }}</RouterLink>
    </div>
  </div>

  <div v-else>
    <DetailHeroBand :lead="listing.type === 'rental' ? 'Rental detail &' : 'Property detail &'" accent="AI price analysis" subtitle="A complete property listing with AI price evaluation, market trends and data-driven insights to help you decide." />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-24 relative z-10 pb-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 main-card rounded-3xl p-4 md:p-5">
          <PageBreadcrumbs class="mb-3" :crumbs="crumbs" />
          <PhotoGrid :images="images" :title="listing.title">
            <template #empty><AppIcon name="home" :size="96" /></template>
          </PhotoGrid>
          <div class="flex flex-wrap items-center gap-3 mt-5 mb-2">
            <h1 class="text-2xl sm:text-3xl font-black" style="color:var(--text-primary)">{{ listing.title }}</h1>
          </div>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm mb-4" style="color:var(--text-muted)">
            <span class="flex items-center gap-1"><AppIcon name="mapPin" :size="14" /> {{ listing.city || '—' }}, North Cyprus</span>
            <span>·</span>
            <PostedAt :listing="listing" />
            <span>·</span>
            <span class="flex items-center gap-1"><AppIcon name="eye" :size="14" /> {{ listing.view_count || 0 }} {{ $t('views') }}</span>
            <span v-if="listing.listing_number" class="ml-auto text-xs font-mono px-2 py-0.5 rounded" style="background:var(--surface-overlay)">ID: {{ listing.listing_number }}</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <span v-for="f in keyFacts" :key="f.label" class="spec-chip"><AppIcon :name="f.icon" :size="14" /> {{ f.value }} <span style="color:var(--text-muted)">{{ $t(f.label) }}</span></span>
          </div>
        </div>
        <div class="lg:sticky lg:top-20">
          <DetailPriceCard :listing="listing" :favorited="isFavorited" :area-sqm="property?.area_sqm ? Number(property.area_sqm) : null" @contact="scrollToSeller" @favorite="toggleFavorite" />
        </div>
      </div>

      <div class="mt-6"><AiDealAnalysis :listing="listing" /></div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 items-stretch">
        <SimilarList :items="comparables" title="Similar listings" />
        <WhyThisScore :listing="listing" />
        <RiskInsight v-if="listing.risk_level" :listing="listing" />
        <div v-else class="rounded-2xl p-5 text-xs" style="background:var(--bg-card);border:1px solid var(--border-subtle);color:var(--text-muted)">
          <h3 class="font-bold text-sm mb-2" style="color:var(--text-primary)">{{ $t('Investment / risk insight') }}</h3>{{ $t('The risk check runs together with the price check.') }}
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6 items-start">
        <div class="lg:col-span-2 space-y-6">
          <!-- Property details -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5 flex items-center gap-2" style="color:var(--text-primary)">
              <AppIcon name="home" :size="20" style="color:var(--accent-cyan)" /> {{ $t('Property Details') }}
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
              <SpecItem v-if="property?.bedrooms" icon="bed" :label="$t('Bedrooms')" :value="String(property.bedrooms)" />
              <SpecItem v-if="property?.bathrooms" icon="bath" :label="$t('Bathrooms')" :value="String(property.bathrooms)" />
              <SpecItem v-if="property?.area_sqm" icon="ruler" :label="$t('Area (m²)')" :value="String(property.area_sqm) + ' m²'" />
              <SpecItem v-if="property?.area_sqft" icon="ruler" :label="$t('Area (ft²)')" :value="String(property.area_sqft) + ' ft²'" />
              <SpecItem v-if="property?.furnishing" icon="home" :label="$t('Furnishing')" :value="capitalize(property.furnishing)" />
              <SpecItem v-if="property?.condition" icon="sparkles" :label="$t('Condition')" :value="capitalize(property.condition)" />
              <SpecItem v-if="property?.floor_number != null" icon="building" :label="$t('Floor')" :value="property.floor_number === 0 ? 'Ground' : String(property.floor_number)" />
              <SpecItem v-if="property?.build_year" icon="calendar" :label="$t('Built')" :value="String(property.build_year)" />
              <SpecItem icon="mapPin" :label="$t('Location')" :value="listing.city" />
            </div>

            <!-- Amenities -->
            <div v-if="amenitiesList.length" class="mt-6 pt-5" style="border-top:1px solid var(--border-subtle)">
              <h3 class="text-sm font-semibold mb-3" style="color:var(--text-secondary)">{{ $t('Amenities') }}</h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <div v-for="a in amenitiesList" :key="a.label" class="flex items-center gap-2 text-xs py-2 px-3 rounded-lg" :style="a.has ? 'background:rgba(var(--accent-teal-rgb),0.06);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.15)' : 'background:var(--surface-overlay-soft);color:var(--text-muted);border:1px solid var(--surface-overlay)'">
                  <span>{{ a.has ? '✓' : '✗' }}</span> {{ $t(a.label) }}
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4" style="color:var(--text-primary)">{{ $t('Description') }}</h2>
            <DescriptionTabs :description="listing.description" :translations="listing.description_i18n" />
          </div>

          <!-- Location info -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="mapPin" :size="20" /> {{ $t('Location') }}</h2>
            <p class="text-sm" style="color:var(--text-secondary)">{{ listing.city }}<span v-if="listing.district">, {{ listing.district }}</span> · {{ $t('North Cyprus') }}</p>
            <div v-if="nearbyLocations.length" class="grid grid-cols-3 gap-4 mt-4">
              <div v-for="loc in nearbyLocations" :key="loc.label" class="rounded-xl p-3 text-center" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
                <AppIcon :name="loc.icon" :size="20" class="mx-auto mb-1" />
                <div class="text-sm font-bold" style="color:var(--text-primary)">{{ loc.dist }}</div>
                <div class="text-xs" style="color:var(--text-muted)">{{ $t(loc.label) }}</div>
              </div>
            </div>
          </div>

          <div v-if="comparables.length" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-bold text-lg" style="color:var(--text-primary)">{{ $t('Similar properties in North Cyprus') }}</h2>
              <RouterLink :to="listing.type === 'rental' ? '/rentals' : '/properties'" class="text-xs font-semibold" style="color:var(--accent-cyan)">{{ $t('View all') }} →</RouterLink>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <ListingCard v-for="c in comparables.slice(0, 3)" :key="c.id" :listing="c" />
            </div>
          </div>
          <BetterOptionsBar :listing-id="listing.id" />
        </div>

        <div class="space-y-5">
          <div id="seller" class="rounded-2xl p-5 scroll-mt-24" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="font-bold text-sm mb-3 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="user" :size="18" /> {{ $t('Seller') }}</h3>
            <SellerContactCard :listing-id="listing.id" :contact="sellerContact" :kind="listing.type">
              <template #secondary>
                <button @click="toggleFavorite" class="w-full py-3 rounded-xl font-semibold text-sm flex items-center justify-center gap-2" style="background:var(--surface-overlay);color:var(--text-primary);border:1px solid var(--border-subtle)">
                  <AppIcon name="heart" :size="18" :fill="isFavorited ? 'currentColor' : 'none'" /> {{ isFavorited ? $t('Saved') : $t('Save listing') }}
                </button>
              </template>
            </SellerContactCard>
          </div>

          <!-- Why buy in NC -->
          <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="text-sm font-bold mb-3 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="star" :size="18" /> {{ $t('Why North Cyprus?') }}</h3>
            <ul class="space-y-2">
              <li v-for="r in whyNC" :key="r" class="text-xs flex items-start gap-2" style="color:var(--text-muted)">
                <span style="color:var(--accent-cyan);flex-shrink:0">✓</span> {{ r }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import SpecItem from '@/components/listings/SpecItem.vue'
import PostedAt from '@/components/listings/PostedAt.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import SellerContactCard, { type SellerContact } from '@/components/listings/SellerContactCard.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useListingsStore } from '@/stores/listings'
import AiDealAnalysis from '@/components/listings/AiDealAnalysis.vue'
import RiskInsight from '@/components/listings/RiskInsight.vue'
import DescriptionTabs from '@/components/listings/DescriptionTabs.vue'
import PageBreadcrumbs from '@/components/listings/PageBreadcrumbs.vue'
import PhotoGrid from '@/components/listings/PhotoGrid.vue'
import DetailHeroBand from '@/components/listings/DetailHeroBand.vue'
import DetailPriceCard from '@/components/listings/DetailPriceCard.vue'
import WhyThisScore from '@/components/listings/WhyThisScore.vue'
import SimilarList from '@/components/listings/SimilarList.vue'
import BetterOptionsBar from '@/components/listings/BetterOptionsBar.vue'
import ListingCard from '@/components/listings/ListingCard.vue'
import { t } from '@/i18n'
import type { Listing, Property } from '@/types'


const route = useRoute()
const store = useListingsStore()
const loading = ref(true)
const listing = ref<Listing | null>(null)
const comparables = ref<any[]>([])
const isFavorited = ref(false)
const activeImage = ref<string | null>(null)
const sellerContact = ref<SellerContact | null>(null)

const property = computed(() => listing.value?.listable as Property | null)
const images = computed(() => { const l = listing.value as any; return l?.images?.length ? l.images : (l?.thumbnail ? [l.thumbnail] : []) })

const keyFacts = computed(() => {
  const p = property.value
  const l = listing.value
  if (!p || !l) return []
  return [
    { icon: 'bed', label: 'Bedrooms', value: String(p.bedrooms ?? '—') },
    { icon: 'bath', label: 'Bathrooms', value: String(p.bathrooms ?? '—') },
    { icon: 'ruler', label: 'Size', value: p.area_sqm ? p.area_sqm + ' m²' : '—' },
    { icon: 'home', label: 'Furnished', value: p.furnishing ? capitalize(p.furnishing) : '—' },
  ]
})

const amenitiesList = computed(() => {
  const p = property.value
  return [
    { label: 'Parking', has: p?.has_parking },
    { label: 'Garden', has: p?.has_garden },
    { label: 'Pool', has: p?.has_pool },
    { label: 'Elevator', has: p?.has_elevator },
  ].filter((a) => a.has !== undefined && a.has !== null)
})

const nearbyLocations = computed(() => {
  const nearby = (property.value as any)?.nearby_locations ?? {}
  return [
    { icon: 'plane', label: 'Airport', dist: nearby.airport },
    { icon: 'mapPin', label: 'Beach', dist: nearby.beach },
    { icon: 'building', label: 'City Centre', dist: nearby.city_centre },
  ].filter((l) => !!l.dist)
})



const crumbs = computed(() => {
  const typeName = (property.value as any)?.property_type?.name ?? (property.value as any)?.propertyType?.name
  const isRental = listing.value?.type === 'rental'
  return [
    { label: t(isRental ? 'Rentals' : 'Properties'), to: isRental ? '/rentals' : '/properties' },
    ...(typeName ? [{ label: typeName, to: `${isRental ? '/rentals' : '/properties'}?type=${encodeURIComponent(typeName)}` }] : []),
    { label: listing.value?.title ?? '' },
  ]
})

function capitalize(s: string) { return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '' }

function scrollToSeller() {
  document.getElementById('seller')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function toggleFavorite() { try { await store.toggleFavorite(listing.value!.id); isFavorited.value = !isFavorited.value } catch {} }

const whyNC = [
  'Mediterranean climate and a growing expat community',
  'Prices are often lower than comparable EU/UK coastal markets',
  'Rental demand from students and tourists in many areas',
  'Tax rules and title-deed types vary: get independent legal advice before buying',
]

async function load() {
  loading.value = true
  try {
    const res = await store.fetchListing(route.params.slug as string) as any
    listing.value = res.listing ?? res
    comparables.value = res.comparable_listings ?? []
    isFavorited.value = res.is_favorited ?? false
    sellerContact.value = res.seller_contact ?? null
    const imgs = (listing.value as any)?.images
    activeImage.value = imgs?.[0] ?? (listing.value as any)?.thumbnail ?? null
  } catch { listing.value = null } finally { loading.value = false }
}

onMounted(load)
watch(() => route.params.slug, load)
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; overflow: hidden; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; max-height: 0; }
.slide-down-enter-to, .slide-down-leave-from { opacity: 1; max-height: 400px; }
</style>

<style scoped>
.main-card { background: var(--bg-card); border: 1px solid var(--border-subtle); box-shadow: 0 18px 48px rgba(8,24,39,.12); }
.spec-chip { display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:600;color:var(--text-secondary);background:var(--bg-card);border:1px solid var(--border-strong) }
</style>
