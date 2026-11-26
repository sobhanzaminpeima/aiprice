<template>
  <div v-if="loading" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <div class="w-12 h-12 rounded-full border-2 border-t-[var(--accent-teal)] animate-spin mx-auto mb-4" style="border-color:rgba(var(--accent-teal-rgb),0.2);border-top-color:var(--accent-teal)"></div>
      <p style="color:var(--text-muted)">{{ $t('Loading listing...') }}</p>
    </div>
  </div>

  <div v-else-if="!listing" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <AppIcon name="car" :size="64" class="mx-auto mb-4" />
      <h2 class="text-xl font-bold mb-2" style="color:var(--text-primary)">{{ $t('Listing Not Found') }}</h2>
      <RouterLink to="/cars" class="btn-primary mt-4 inline-block">{{ $t('Browse Cars') }}</RouterLink>
    </div>
  </div>

  <div v-else>
    <DetailHeroBand lead="Vehicle detail &" accent="AI price analysis" subtitle="A complete vehicle listing with AI price evaluation, market trends and data-driven insights to help you decide." />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-24 relative z-10 pb-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Gallery and headline -->
        <div class="lg:col-span-2 main-card rounded-3xl p-4 md:p-5">
          <PageBreadcrumbs class="mb-3" :crumbs="crumbs" />
          <PhotoGrid :images="images" :title="listing.title">
            <template #empty><AppIcon name="car" :size="96" /></template>
            <template #overlay>
              <span v-if="listing.is_featured" class="absolute top-3 left-3 ai-badge flex items-center gap-1"><AppIcon name="star" :size="14" /> {{ $t('Featured') }}</span>
            </template>
          </PhotoGrid>
          <div class="flex flex-wrap items-center gap-3 mt-5 mb-2">
            <h1 class="text-2xl sm:text-3xl font-black" style="color:var(--text-primary)">{{ listing.title }}</h1>
            <span v-if="listing.ai_price_status && listing.ai_price_status !== 'insufficient_data'" class="px-3 py-1 rounded-full text-sm font-bold" :style="aiStatusStyle">{{ $t(aiStatusLabel) }}</span>
            <span v-else-if="sourceAttribution" class="px-3 py-1 rounded-full text-sm font-bold" style="background:var(--surface-overlay);color:var(--text-muted)">{{ $t('AI analysis pending') }}</span>
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
            <span v-if="vehicle?.body_type" class="spec-chip"><AppIcon name="car" :size="14" /> {{ $t(capitalize(vehicle.body_type)) }}</span>
            <span v-if="vehicle?.year" class="spec-chip"><AppIcon name="calendar" :size="14" /> {{ vehicle.year }}</span>
            <span v-if="vehicle?.fuel_type" class="spec-chip"><AppIcon name="fuel" :size="14" /> {{ $t(capitalize(vehicle.fuel_type)) }}</span>
            <span v-if="vehicle?.transmission" class="spec-chip"><AppIcon name="settings" :size="14" /> {{ $t(capitalize(vehicle.transmission)) }}</span>
            <span v-if="vehicle?.mileage" class="spec-chip"><AppIcon name="gauge" :size="14" /> ≈ {{ Number(vehicle.mileage).toLocaleString() }} km</span>
          </div>
        </div>

        <!-- Price card -->
        <div class="lg:sticky lg:top-20">
          <DetailPriceCard :listing="listing" :favorited="isFavorited" :asking="!!sourceAttribution" @contact="scrollToSeller" @favorite="toggleFavorite" />
        </div>
      </div>

      <div class="mt-6"><AiDealAnalysis :listing="listing" /></div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 items-stretch">
        <SimilarList :items="comparables" title="Similar listings" />
        <WhyThisScore :listing="listing" />
        <RiskInsight v-if="listing.risk_level" :listing="listing" />
        <div v-else class="rounded-2xl p-5 text-xs" style="background:var(--bg-card);border:1px solid var(--border-subtle);color:var(--text-muted)">
          <h3 class="font-bold text-sm mb-2" style="color:var(--text-primary)">{{ $t('Risk insight') }}</h3>{{ $t('The risk check runs together with the price check.') }}
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6 items-start">
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="car" :size="20" style="color:var(--accent-cyan)" /> {{ $t('Vehicle Specifications') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
              <SpecItem icon="calendar" :label="$t('Year')" :value="vehicle?.year ? String(vehicle.year) : '—'" />
              <SpecItem icon="car" :label="$t('Body Type')" :value="vehicle?.body_type ? $t(capitalize(vehicle.body_type)) : '—'" />
              <SpecItem icon="fuel" :label="$t('Fuel')" :value="vehicle?.fuel_type ? $t(capitalize(vehicle.fuel_type)) : '—'" />
              <SpecItem icon="settings" :label="$t('Transmission')" :value="vehicle?.transmission ? $t(capitalize(vehicle.transmission)) : '—'" />
              <SpecItem icon="gauge" :label="$t('Mileage')" :value="vehicle?.mileage ? '≈ ' + Number(vehicle.mileage).toLocaleString() + ' km' : '—'" />
              <SpecItem icon="check" :label="$t('Condition')" :value="vehicle?.condition ? $t(capitalize(vehicle.condition)) : $t('Not specified')" />
              <SpecItem icon="mapPin" :label="$t('Location')" :value="listing.city || '—'" />
              <SpecItem v-if="vehicle?.color" icon="palette" :label="$t('Color')" :value="vehicle.color" />
              <SpecItem v-if="vehicle?.engine_size" icon="wrench" :label="$t('Engine')" :value="engineLabel(vehicle.engine_size)" />
            </div>
            <div v-if="vehicleFeatures.length" class="mt-5 pt-4 flex flex-wrap gap-2" style="border-top:1px solid var(--border-subtle)">
              <span v-for="f in vehicleFeatures" :key="f" class="px-2.5 py-1 rounded-lg text-xs font-medium" style="background:rgba(var(--accent-teal-rgb),0.07);color:var(--accent-teal)">✓ {{ formatFeature(f) }}</span>
            </div>
          </div>

          <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-3" style="color:var(--text-primary)">{{ $t('Description') }}</h2>
            <DescriptionTabs :description="listing.description" :translations="listing.description_i18n" />
          </div>

          <div v-if="comparables.length" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-bold text-lg" style="color:var(--text-primary)">{{ $t('Similar vehicles in North Cyprus') }}</h2>
              <RouterLink to="/cars" class="text-xs font-semibold" style="color:var(--accent-cyan)">{{ $t('View all similar vehicles') }} →</RouterLink>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <ListingCard v-for="c in comparables.slice(0, 3)" :key="c.id" :listing="c" :match="similarity(listing, c)" />
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

          <div v-if="sourceAttribution" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="text-sm font-bold mb-2 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="globe" :size="18" /> {{ $t('About this listing') }}</h3>
            <p class="text-xs mb-2" style="color:var(--text-secondary)">
              <template v-if="sourceAttribution.source_name">{{ $t('Originally advertised on {site}.', { site: sourceAttribution.source_name }) }} </template>
              {{ $t(sourceAttribution.note) }}
            </p>
            <a v-if="sourceAttribution.original_url" :href="sourceAttribution.original_url" target="_blank" rel="noopener nofollow" class="text-xs font-semibold" style="color:var(--accent-cyan)">{{ $t('View the original advert and seller contact') }} →</a>
          </div>

          <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="text-sm font-bold mb-3 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="shield" :size="18" /> {{ $t('Safety Tips') }}</h3>
            <ul class="space-y-2">
              <li v-for="tip in safetyTips" :key="tip" class="text-xs" style="color:var(--text-muted)">• {{ $t(tip) }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { t } from '@/i18n'
import PostedAt from '@/components/listings/PostedAt.vue'
import SpecItem from '@/components/listings/SpecItem.vue'
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
import { similarity } from '@/components/listings/similarity'
import type { Listing, Vehicle } from '@/types'

// Sub-components defined inline

const route = useRoute()
const store = useListingsStore()

const loading = ref(true)
const listing = ref<Listing | null>(null)
const comparables = ref<any[]>([])
const isFavorited = ref(false)
const activeImage = ref<string | null>(null)
const sellerContact = ref<SellerContact | null>(null)
const sourceAttribution = ref<{ source_name: string | null; original_url: string | null; note: string } | null>(null)

/** Plain numbers get a unit (>20 means cc, otherwise litres); text such as "1.6 L" is shown as entered. */
function engineLabel(v: string | number): string {
  const s = String(v).trim()
  if (!/^\d+(\.\d+)?$/.test(s)) return s
  const n = Number(s)
  return n > 20 ? `${n.toLocaleString()} cc` : `${s} L`
}

const vehicle = computed(() => listing.value?.listable as Vehicle | null)
const images = computed(() => {
  const l = listing.value as any
  return l?.images?.length ? l.images : (l?.thumbnail ? [l.thumbnail] : [])
})

const vehicleFeatures = computed(() => {
  const f = vehicle.value?.features
  if (Array.isArray(f)) return f
  if (typeof f === 'object' && f) return Object.keys(f).filter(k => (f as any)[k])
  return []
})


const aiStatusStyle = computed(() => {
  const s = listing.value?.ai_price_status
  if (s === 'fair') return 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.3)'
  return 'background:rgba(var(--warning-rgb),0.12);color:var(--warning);border:1px solid rgba(var(--warning-rgb),0.3)'
})
const aiStatusLabel = computed(() => {
  const map: Record<string, string> = { fair: '✓ Fair price (estimate)', slightly_overpriced: '⚠ Slightly high', overpriced: '✗ High for this market', insufficient_data: 'Waiting for similar listings' }
  return map[listing.value?.ai_price_status ?? ''] ?? ''
})

const crumbs = computed(() => {
  const v = vehicle.value as any
  const brand = v?.brand?.name, model = v?.model?.name
  return [
    { label: t('Cars'), to: '/cars' },
    ...(brand ? [{ label: brand, to: `/cars?brand=${encodeURIComponent(brand)}` }] : []),
    ...(brand && model ? [{ label: model, to: `/cars?brand=${encodeURIComponent(brand)}&search=${encodeURIComponent(model)}` }] : []),
    { label: listing.value?.title ?? '' },
  ]
})

function capitalize(s: string) { return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '' }
function formatFeature(f: string) { return f.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }

function scrollToSeller() {
  document.getElementById('seller')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function toggleFavorite() {
  try { await store.toggleFavorite(listing.value!.id); isFavorited.value = !isFavorited.value } catch {}
}


const safetyTips = [
  'Always inspect the vehicle in person before payment',
  'Check title deed and registration documents carefully',
  'Treat the AI Trust Score as a guide, not proof of authenticity',
  'Never transfer money before viewing the vehicle',
  'Meet in a safe, public location for test drives',
]

async function load() {
  loading.value = true
  try {
    const res = await store.fetchListing(route.params.slug as string)
    const data = res as any
    listing.value = data.listing ?? data
    comparables.value = data.comparable_listings ?? []
    isFavorited.value = data.is_favorited ?? false
    sellerContact.value = data.seller_contact ?? null
    sourceAttribution.value = data.source_attribution ?? null
    const imgs = (listing.value as any).images
    activeImage.value = imgs?.[0] ?? (listing.value as any).thumbnail ?? null
  } catch {
    listing.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.slug, load)
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; overflow: hidden; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; max-height: 0; transform: translateY(-8px); }
.slide-down-enter-to, .slide-down-leave-from { opacity: 1; max-height: 400px; }
</style>

<style scoped>
.main-card { background: var(--bg-card); border: 1px solid var(--border-subtle); box-shadow: 0 18px 48px rgba(8,24,39,.12); }
.spec-chip { display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:600;color:var(--text-secondary);background:var(--bg-card);border:1px solid var(--border-strong) }
</style>
