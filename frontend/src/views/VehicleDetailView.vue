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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <PageBreadcrumbs class="mb-4" :crumbs="crumbs" />
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left: Main info -->
        <div class="lg:col-span-2 space-y-6">
          <PhotoGrid :images="images" :title="listing.title">
            <template #empty><AppIcon name="car" :size="96" /></template>
            <template #overlay>
              <span v-if="listing.is_featured" class="absolute top-3 left-3 ai-badge flex items-center gap-1"><AppIcon name="star" :size="14" /> {{ $t('Featured') }}</span>
            </template>
          </PhotoGrid>

          <!-- Title + quick specs -->
          <div>
            <div class="flex flex-wrap items-start gap-3 mb-3">
              <h1 class="text-3xl font-black" style="color:var(--text-primary)">{{ listing.title }}</h1>
              <span v-if="listing.ai_price_status" class="px-3 py-1 rounded-full text-sm font-bold mt-1" :style="aiStatusStyle">{{ $t(aiStatusLabel) }}</span>
              <span v-else-if="sourceAttribution" class="px-3 py-1 rounded-full text-sm font-bold mt-1" style="background:var(--surface-overlay);color:var(--text-muted)">{{ $t('AI analysis pending') }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm" style="color:var(--text-muted)">
              <span class="flex items-center gap-1"><AppIcon name="mapPin" :size="14" /> {{ listing.city }}, North Cyprus</span>
              <span>·</span>
              <PostedAt :listing="listing" full />
              <span>·</span>
              <span class="flex items-center gap-1"><AppIcon name="eye" :size="14" /> {{ listing.view_count || 0 }} {{ $t('views') }}</span>
              <span v-if="listing.listing_number" class="ml-auto text-xs font-mono px-2 py-0.5 rounded" style="background:var(--surface-overlay);color:var(--text-muted)">{{ listing.listing_number }}</span>
            </div>
          </div>

          <!-- Vehicle specs grid -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5 flex items-center gap-2" style="color:var(--text-primary)">
              <AppIcon name="car" :size="20" style="color:var(--accent-teal)" /> {{ $t('Vehicle Specifications') }}
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <SpecItem v-if="vehicle?.year" icon="calendar" :label="$t('Year')" :value="String(vehicle.year)" />
              <SpecItem v-if="vehicle?.mileage" icon="gauge" :label="$t('Mileage')" :value="Number(vehicle.mileage).toLocaleString() + ' km'" />
              <SpecItem v-if="vehicle?.fuel_type" icon="fuel" :label="$t('Fuel')" :value="$t(capitalize(vehicle.fuel_type))" />
              <SpecItem v-if="vehicle?.transmission" icon="settings" :label="$t('Transmission')" :value="$t(capitalize(vehicle.transmission))" />
              <SpecItem v-if="vehicle?.body_type" icon="car" :label="$t('Body Type')" :value="$t(capitalize(vehicle.body_type))" />
              <SpecItem v-if="vehicle?.color" icon="palette" :label="$t('Color')" :value="vehicle.color" />
              <SpecItem v-if="vehicle?.engine_size" icon="wrench" :label="$t('Engine')" :value="engineLabel(vehicle.engine_size)" />
              <SpecItem v-if="vehicle?.condition" icon="check" :label="$t('Condition')" :value="capitalize(vehicle.condition)" />
              <SpecItem icon="globe" :label="$t('Location')" :value="listing.city" />
            </div>

            <!-- Features -->
            <div v-if="vehicleFeatures.length" class="mt-6 pt-5" style="border-top:1px solid var(--border-subtle)">
              <h3 class="text-sm font-semibold mb-3" style="color:var(--text-secondary)">{{ $t('Features & Equipment') }}</h3>
              <div class="flex flex-wrap gap-2">
                <span v-for="f in vehicleFeatures" :key="f" class="px-2.5 py-1 rounded-lg text-xs font-medium" style="background:rgba(var(--accent-teal-rgb),0.07);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.15)">
                  ✓ {{ formatFeature(f) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4" style="color:var(--text-primary)">{{ $t('Description') }}</h2>
            <DescriptionTabs :description="listing.description" :translations="listing.description_i18n" />
          </div>

          <!-- AI Deal Analysis: fair range, signal, confidence, trend, explanation -->
          <AiDealAnalysis :listing="listing" />

          <!-- Why this score? + Show Me Better -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            <AiExplainability :listing="listing" />
            <RiskInsight :listing="listing" />
          </div>

          <!-- Comparable listings -->
          <div v-if="comparables.length" class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5" style="color:var(--text-primary)">{{ $t('Similar Vehicles') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <RouterLink v-for="c in comparables" :key="c.id" :to="`/listings/${c.slug}`" class="flex items-center gap-3 p-3 rounded-xl transition-all" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)" @mouseenter="(e:any) => e.currentTarget.style.borderColor='rgba(var(--accent-teal-rgb),0.2)'" @mouseleave="(e:any) => e.currentTarget.style.borderColor='var(--border-subtle)'">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:var(--surface-overlay)"><AppIcon name="car" :size="20" /></div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium truncate" style="color:var(--text-primary)">{{ c.title }}</p>
                  <p class="text-xs font-bold" style="color:var(--accent-teal)">{{ formatPrice(c.price, c.currency) }}</p>
                </div>
                <span class="text-xs" style="color:var(--text-muted)">→</span>
              </RouterLink>
            </div>
          </div>
        </div>

        <!-- Right: Contact sidebar -->
        <div class="space-y-5">
          <!-- Price card -->
          <div class="price-card-dark rounded-2xl p-6 sticky top-20">
            <div class="mb-4">
              <p v-if="sourceAttribution" class="text-xs mb-1" style="color:var(--text-muted)">{{ $t("Seller's asking price") }}</p>
              <p class="text-3xl font-black mb-1" style="color:var(--text-primary)">{{ formatPrice(listing.price, listing.currency) }}</p>
              <p v-if="sourceAttribution && listing.market_avg_price" class="text-xs" style="color:var(--text-muted)">
                {{ $t('AIPrice estimated market value: {price} (an estimate, separate from the asking price)', { price: formatPrice(listing.market_avg_price, listing.currency) }) }}
              </p>
              <p v-if="listing.price_negotiable" class="text-sm" style="color:var(--accent-teal)">{{ $t('✓ Price is negotiable') }}</p>
            </div>

            <!-- AI fair range, deal signal and confidence -->
            <div v-if="listing.market_min_price && listing.market_max_price && listing.ai_price_status !== 'insufficient_data'" class="mb-4 p-3 rounded-xl" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
              <p class="text-[11px] font-semibold mb-1" style="color:var(--text-muted)">{{ $t('AI fair price range') }}</p>
              <p class="text-lg font-black" style="color:var(--accent-teal)">{{ formatPrice(listing.market_min_price, listing.currency) }} – {{ formatPrice(listing.market_max_price, listing.currency) }}</p>
              <div class="flex items-center justify-between mt-2 gap-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-black" :style="aiStatusStyle">{{ $t(aiStatusLabel) }}</span>
                <div v-if="listing.ai_confidence_score" class="flex items-center gap-2"><ConfidenceRing :value="Number(listing.ai_confidence_score)" :size="40" style="color:var(--text-primary)" /><span class="text-[11px]" style="color:var(--text-muted)">{{ $t('Confidence') }}</span></div>
              </div>
            </div>

            <!-- Battery -->
            <div v-if="listing.price_battery_percent" class="mb-5 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div class="flex items-center justify-between text-xs mb-2">
                <span style="color:var(--text-muted)">{{ $t('Price Fairness Score') }}</span>
                <span :style="`color:${batteryColor};font-weight:700`">{{ listing.price_battery_percent }}%</span>
              </div>
              <div class="h-2 rounded-full" style="background:var(--border-subtle)">
                <div class="h-2 rounded-full transition-all" :style="`width:${listing.price_battery_percent}%;background:${batteryColor}`"></div>
              </div>
            </div>

            <!-- Seller contact: masked phone for guests, full number + "Send to seller" for members -->
            <SellerContactCard :listing-id="listing.id" :contact="sellerContact" :kind="listing.type">
              <template #secondary>
                <button @click="toggleFavorite" class="w-full py-3.5 rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2" :style="isFavorited ? 'background:rgba(var(--danger-rgb),0.12);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.25)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-strong)'">
                  <AppIcon name="heart" :size="18" :fill="isFavorited ? 'currentColor' : 'none'" /> {{ isFavorited ? $t('Saved') : $t('Save listing') }}
                </button>
              </template>
            </SellerContactCard>
          </div>

          <!-- Imported listing: provenance and verification notice -->
          <div v-if="sourceAttribution" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="text-sm font-bold mb-2 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="globe" :size="18" /> {{ $t('About this listing') }}</h3>
            <p class="text-xs mb-2" style="color:var(--text-secondary)">
              <template v-if="sourceAttribution.source_name">{{ $t('Originally advertised on {site}.', { site: sourceAttribution.source_name }) }} </template>
              {{ $t(sourceAttribution.note) }}
            </p>
            <a v-if="sourceAttribution.original_url" :href="sourceAttribution.original_url" target="_blank" rel="noopener nofollow" class="text-xs font-semibold" style="color:var(--accent-cyan)">
              {{ $t('View the original advert and seller contact') }} →
            </a>
          </div>

          <!-- Safety tips -->
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
import AiExplainability from '@/components/listings/AiExplainability.vue'
import AiDealAnalysis from '@/components/listings/AiDealAnalysis.vue'
import RiskInsight from '@/components/listings/RiskInsight.vue'
import DescriptionTabs from '@/components/listings/DescriptionTabs.vue'
import PageBreadcrumbs from '@/components/listings/PageBreadcrumbs.vue'
import PhotoGrid from '@/components/listings/PhotoGrid.vue'
import ConfidenceRing from '@/components/listings/ConfidenceRing.vue'
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
const batteryColor = computed(() => {
  const p = listing.value?.price_battery_percent ?? 0
  return p >= 85 ? 'var(--accent-teal)' : p >= 70 ? 'var(--accent-cyan)' : p >= 50 ? 'var(--warning)' : 'var(--danger)'
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
function formatPrice(val: number, currency = 'GBP') {
  try { return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', maximumFractionDigits: 0 }).format(val) }
  catch { return `${currency} ${Number(val)?.toLocaleString()}` }
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
.price-card-dark {
  background: linear-gradient(160deg, #0a2540 0%, #08304f 60%, #0a3b52 100%);
  border: 1px solid rgba(120,190,255,.25); box-shadow: 0 18px 48px rgba(2,10,22,.3);
  /* Re-theme everything inside (including the seller contact card) for a dark surface. */
  --text-primary: #ffffff; --text-secondary: #dce9f7; --text-muted: #a9bdd3;
  --border-subtle: rgba(255,255,255,.16); --border-strong: rgba(255,255,255,.28);
  --surface-overlay: rgba(255,255,255,.09); --surface-overlay-soft: rgba(255,255,255,.05);
  --bg-card: rgba(255,255,255,.06); --bg-surface: rgba(255,255,255,.06);
  --accent-teal: #4fe0b0; --accent-teal-rgb: 79,224,176; --accent-cyan: #12C5E8; --accent-cyan-rgb: 18,197,232;
  --on-accent: #04111d;
  color: #fff;
}
</style>
