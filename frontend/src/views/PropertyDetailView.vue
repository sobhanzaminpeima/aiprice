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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <PageBreadcrumbs class="mb-4" :crumbs="crumbs" />
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left main -->
        <div class="lg:col-span-2 space-y-6">
          <PhotoGrid :images="images" :title="listing.title">
            <template #empty><AppIcon name="home" :size="96" /></template>
          </PhotoGrid>

          <div>
            <h1 class="text-3xl font-black mb-2" style="color:var(--text-primary)">{{ listing.title }}</h1>
            <div class="flex flex-wrap items-center gap-3 text-sm" style="color:var(--text-muted)">
              <span class="flex items-center gap-1"><AppIcon name="mapPin" :size="14" /> {{ listing.city }}, North Cyprus</span>
              <span>·</span>
              <PostedAt :listing="listing" full />
              <span v-if="property?.bedrooms">· {{ property.bedrooms }} {{ $t('bed') }}</span>
              <span v-if="property?.bathrooms">· {{ property.bathrooms }} {{ $t('bath') }}</span>
              <span v-if="property?.area_sqm">· {{ property.area_sqm }}m²</span>
            </div>
          </div>

          <!-- Key facts ribbon -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div v-for="f in keyFacts" :key="f.label" class="rounded-xl p-4 text-center" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
              <AppIcon :name="f.icon" :size="24" class="mx-auto mb-1" />
              <div class="text-base font-black" style="color:var(--text-primary)">{{ f.value }}</div>
              <div class="text-xs mt-0.5" style="color:var(--text-muted)">{{ $t(f.label) }}</div>
            </div>
          </div>

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

          <!-- Description -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4" style="color:var(--text-primary)">{{ $t('About This Property') }}</h2>
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

          <!-- AI Deal Analysis: fair range, signal, confidence, trend, explanation -->
          <AiDealAnalysis :listing="listing" />

          <!-- Why this score? + Show Me Better -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            <AiExplainability :listing="listing" />
            <RiskInsight :listing="listing" />
          </div>

          <!-- Similar properties -->
          <div v-if="comparables.length" class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5" style="color:var(--text-primary)">{{ $t('Similar Properties') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <RouterLink v-for="c in comparables" :key="c.id" :to="`/listings/${c.slug}`" class="flex items-center gap-3 p-3 rounded-xl transition-all" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)" @mouseenter="(e:any) => e.currentTarget.style.borderColor='rgba(var(--accent-cyan-rgb),0.2)'" @mouseleave="(e:any) => e.currentTarget.style.borderColor='var(--border-subtle)'">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:var(--surface-overlay)"><AppIcon name="home" :size="20" /></div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium truncate" style="color:var(--text-primary)">{{ c.title }}</p>
                  <p class="text-xs font-bold" style="color:var(--accent-cyan)">{{ formatPrice(c.price, c.currency) }}</p>
                </div>
              </RouterLink>
            </div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="space-y-5">
          <div class="price-card-dark rounded-2xl p-6 sticky top-20">
            <p class="text-3xl font-black mb-0.5" style="color:var(--text-primary)">{{ formatPrice(listing.price, listing.currency) }}</p>
            <p v-if="property?.area_sqm" class="text-sm mb-4" style="color:var(--text-muted)">{{ formatPrice(listing.price / property.area_sqm, listing.currency) }}/m²</p>
            <p v-if="listing.price_negotiable" class="text-sm mb-4" style="color:var(--accent-cyan)">{{ $t('✓ Price negotiable') }}</p>

            <!-- AI fair range, deal signal and confidence -->
            <div v-if="listing.market_min_price && listing.market_max_price && listing.ai_price_status !== 'insufficient_data'" class="mb-4 p-3 rounded-xl" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
              <p class="text-[11px] font-semibold mb-1" style="color:var(--text-muted)">{{ $t('AI fair price range') }}</p>
              <p class="text-lg font-black" style="color:var(--accent-teal)">{{ formatPrice(listing.market_min_price, listing.currency) }} – {{ formatPrice(listing.market_max_price, listing.currency) }}</p>
              <div v-if="listing.ai_confidence_score" class="flex items-center gap-2 mt-2"><ConfidenceRing :value="Number(listing.ai_confidence_score)" :size="40" style="color:var(--text-primary)" /><span class="text-[11px]" style="color:var(--text-muted)">{{ $t('Confidence') }}</span></div>
            </div>

            <!-- Battery -->
            <div v-if="listing.price_battery_percent" class="mb-5 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div class="flex justify-between text-xs mb-2">
                <span style="color:var(--text-muted)">{{ $t('Investment Score') }}</span>
                <span :style="`color:${batteryColor};font-weight:700`">{{ listing.price_battery_percent }}%</span>
              </div>
              <div class="h-2 rounded-full" style="background:var(--border-subtle)">
                <div class="h-2 rounded-full" :style="`width:${listing.price_battery_percent}%;background:${batteryColor}`"></div>
              </div>
            </div>

            <!-- Seller contact: masked phone for guests, full number + "Send to seller" for members -->
            <SellerContactCard :listing-id="listing.id" :contact="sellerContact" :kind="listing.type">
              <template #secondary>
                <button @click="toggleFavorite" class="w-full py-3.5 rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2" :style="isFavorited ? 'background:rgba(var(--danger-rgb),0.12);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.25)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-strong)'">
                  <AppIcon name="heart" :size="18" :fill="isFavorited ? 'currentColor' : 'none'" /> {{ isFavorited ? 'Saved' : 'Save Property' }}
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
import AiExplainability from '@/components/listings/AiExplainability.vue'
import AiDealAnalysis from '@/components/listings/AiDealAnalysis.vue'
import RiskInsight from '@/components/listings/RiskInsight.vue'
import DescriptionTabs from '@/components/listings/DescriptionTabs.vue'
import PageBreadcrumbs from '@/components/listings/PageBreadcrumbs.vue'
import PhotoGrid from '@/components/listings/PhotoGrid.vue'
import ConfidenceRing from '@/components/listings/ConfidenceRing.vue'
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


const batteryColor = computed(() => { const p = listing.value?.price_battery_percent ?? 0; return p >= 85 ? 'var(--accent-teal)' : p >= 70 ? 'var(--accent-cyan)' : 'var(--warning)' })

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
function formatPrice(val: number, currency = 'GBP') {
  try { return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', maximumFractionDigits: 0 }).format(val) }
  catch { return `${currency} ${Number(val)?.toLocaleString()}` }
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
.price-card-dark {
  background: linear-gradient(160deg, #0a2540 0%, #08304f 60%, #0a3b52 100%);
  border: 1px solid rgba(120,190,255,.25); box-shadow: 0 18px 48px rgba(2,10,22,.3);
  --text-primary: #ffffff; --text-secondary: #dce9f7; --text-muted: #a9bdd3;
  --border-subtle: rgba(255,255,255,.16); --border-strong: rgba(255,255,255,.28);
  --surface-overlay: rgba(255,255,255,.09); --surface-overlay-soft: rgba(255,255,255,.05);
  --bg-card: rgba(255,255,255,.06); --bg-surface: rgba(255,255,255,.06);
  --accent-teal: #4fe0b0; --accent-teal-rgb: 79,224,176; --accent-cyan: #12C5E8; --accent-cyan-rgb: 18,197,232;
  --on-accent: #04111d;
  color: #fff;
}
</style>
