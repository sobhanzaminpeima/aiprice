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
    <!-- Hero image gallery -->
    <div class="relative" style="height:480px;background:var(--bg-surface)">
      <img v-if="activeImage" :src="activeImage" :alt="listing.title" class="w-full h-full object-cover" @error="activeImageError = true" />
      <div v-else class="w-full h-full flex items-center justify-center opacity-20"><AppIcon name="car" :size="112" /></div>
      <!-- Gradient overlay -->
      <div class="absolute inset-0" style="background:linear-gradient(to bottom,rgba(5,7,11,0.2) 0%,transparent 40%,rgba(5,7,11,0.8) 100%)"></div>

      <!-- Image thumbnails row -->
      <div v-if="images.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
        <button v-for="(img, i) in images" :key="i" @click="setActiveImage(img)" :aria-label="`Show photo ${Number(i) + 1}`" class="w-14 h-10 rounded-lg overflow-hidden border-2 transition" :style="activeImage === img ? 'border-color:var(--accent-teal)' : 'border-color:rgba(255,255,255,0.2)'">
          <img :src="img" :alt="`${listing.title} — photo ${Number(i) + 1}`" loading="lazy" class="w-full h-full object-cover" />
        </button>
      </div>

      <!-- Back -->
      <RouterLink to="/cars" class="absolute top-5 left-5 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition" style="background:rgba(0,0,0,0.5);color:var(--text-primary);backdrop-filter:blur(8px)">
        {{ $t('← Back to Cars') }}
      </RouterLink>

      <!-- Featured badge -->
      <div v-if="listing.is_featured" class="absolute top-5 right-5 ai-badge flex items-center gap-1"><AppIcon name="star" :size="14" /> {{ $t('Featured') }}</div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left: Main info -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Title + quick specs -->
          <div>
            <div class="flex flex-wrap items-start gap-3 mb-3">
              <h1 class="text-3xl font-black" style="color:var(--text-primary)">{{ listing.title }}</h1>
              <span v-if="listing.ai_price_status" class="px-3 py-1 rounded-full text-sm font-bold mt-1" :style="aiStatusStyle">{{ $t(aiStatusLabel) }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm" style="color:var(--text-muted)">
              <span class="flex items-center gap-1"><AppIcon name="mapPin" :size="14" /> {{ listing.city }}, North Cyprus</span>
              <span>·</span>
              <span>{{ $t('Listed {when}', { when: listedAgo }) }}</span>
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
              <SpecItem v-if="vehicle?.engine_size" icon="wrench" :label="$t('Engine')" :value="Number(vehicle.engine_size) > 20 ? Number(vehicle.engine_size).toLocaleString() + ' cc' : vehicle.engine_size + ' L'" />
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
            <p class="text-sm leading-relaxed whitespace-pre-line" style="color:var(--text-secondary)">{{ listing.description }}</p>
          </div>

          <!-- AI Price Analysis -->
          <div class="rounded-2xl p-6" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.04),rgba(var(--accent-cyan-rgb),0.02));border:1px solid rgba(var(--accent-teal-rgb),0.15)">
            <div class="flex items-center gap-3 mb-5">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('AI') }}</div>
              <h2 class="font-bold text-lg" style="color:var(--text-primary)">{{ $t('AI Price Intelligence') }}</h2>
            </div>

            <!-- Price range bar -->
            <div v-if="listing.market_min_price && listing.market_max_price" class="mb-6">
              <div class="flex items-center justify-between text-xs mb-2" style="color:var(--text-muted)">
                <span>Typical low: {{ formatPrice(listing.market_min_price, listing.currency) }}</span>
                <span>Typical high: {{ formatPrice(listing.market_max_price, listing.currency) }}</span>
              </div>
              <div class="relative h-3 rounded-full" style="background:var(--border-subtle)">
                <div class="absolute inset-y-0 rounded-full" style="background:linear-gradient(90deg,rgba(var(--accent-teal-rgb),0.3),rgba(var(--accent-cyan-rgb),0.3))" :style="{ left: '0%', right: '0%' }"></div>
                <!-- Price marker -->
                <div class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full border-2 shadow-lg" style="background:var(--accent-teal);border-color:var(--on-accent);box-shadow:0 0 12px rgba(var(--accent-teal-rgb),0.6)" :style="{ left: pricePosition + '%', transform: 'translateX(-50%) translateY(-50%)' }"></div>
              </div>
              <p class="text-xs mt-2 text-center" style="color:var(--text-muted)">
                {{ $t('This vehicle is priced at') }} <span :style="`color:${aiColor};font-weight:700`">{{ formatPrice(listing.price, listing.currency) }}</span>
                <template v-if="listing.market_avg_price"> {{ $t('vs typical asking price') }} <span style="color:var(--text-secondary);font-weight:600">{{ formatPrice(listing.market_avg_price, listing.currency) }}</span></template>
              </p>
            </div>

            <!-- Score grid -->
            <div class="grid grid-cols-3 gap-4">
              <ScoreGauge v-if="listing.ai_trust_score" :label="$t('Trust Score')" :value="listing.ai_trust_score" :color="trustColor" />
              <ScoreGauge v-if="listing.ai_investment_score" :label="$t('Investment')" :value="listing.ai_investment_score" color="var(--accent-violet)" />
              <ScoreGauge v-if="listing.ai_confidence_score" :label="$t('Confidence')" :value="listing.ai_confidence_score" color="var(--accent-cyan)" />
            </div>
          </div>

          <!-- Why this score? + Show Me Better -->
          <AiExplainability :listing="listing" />

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
          <div class="rounded-2xl p-6 sticky top-20" style="background:var(--bg-card);border:1px solid rgba(var(--accent-teal-rgb),0.2)">
            <div class="mb-4">
              <p class="text-3xl font-black mb-1" style="color:var(--text-primary)">{{ formatPrice(listing.price, listing.currency) }}</p>
              <p v-if="listing.price_negotiable" class="text-sm" style="color:var(--accent-teal)">{{ $t('✓ Price is negotiable') }}</p>
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
import { locale } from '@/i18n'
import SpecItem from '@/components/listings/SpecItem.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import SellerContactCard, { type SellerContact } from '@/components/listings/SellerContactCard.vue'
import ScoreGauge from '@/components/listings/ScoreGauge.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useListingsStore } from '@/stores/listings'
import AiExplainability from '@/components/listings/AiExplainability.vue'
import type { Listing, Vehicle } from '@/types'

// Sub-components defined inline

const route = useRoute()
const store = useListingsStore()

const loading = ref(true)
const listing = ref<Listing | null>(null)
const comparables = ref<any[]>([])
const isFavorited = ref(false)
const activeImage = ref<string | null>(null)
const activeImageError = ref(false)
const sellerContact = ref<SellerContact | null>(null)

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

const pricePosition = computed(() => {
  if (!listing.value?.market_min_price || !listing.value?.market_max_price) return 50
  const range = listing.value.market_max_price - listing.value.market_min_price
  if (range <= 0) return 50
  const pos = ((listing.value.price - listing.value.market_min_price) / range) * 100
  return Math.max(2, Math.min(98, pos))
})

const aiStatusStyle = computed(() => {
  const s = listing.value?.ai_price_status
  if (s === 'fair') return 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.3)'
  return 'background:rgba(var(--warning-rgb),0.12);color:var(--warning);border:1px solid rgba(var(--warning-rgb),0.3)'
})
const aiStatusLabel = computed(() => {
  const map: Record<string, string> = { fair: '✓ Fair price (estimate)', slightly_overpriced: '⚠ Slightly high', overpriced: '✗ High for this market', insufficient_data: 'Not enough data yet' }
  return map[listing.value?.ai_price_status ?? ''] ?? ''
})
const aiColor = computed(() => listing.value?.ai_price_status === 'fair' ? 'var(--accent-teal)' : 'var(--warning)')
const trustColor = computed(() => {
  const s = listing.value?.ai_trust_score ?? 0
  return s >= 80 ? 'var(--accent-teal)' : s >= 60 ? 'var(--accent-cyan)' : s >= 40 ? 'var(--warning)' : 'var(--danger)'
})
const batteryColor = computed(() => {
  const p = listing.value?.price_battery_percent ?? 0
  return p >= 85 ? 'var(--accent-teal)' : p >= 70 ? 'var(--accent-cyan)' : p >= 50 ? 'var(--warning)' : 'var(--danger)'
})

const listedAgo = computed(() => {
  if (!listing.value?.published_at) return ''
  const days = Math.floor((Date.now() - new Date(listing.value.published_at).getTime()) / 86400000)
  return new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }).format(-days, 'day')
})

function setActiveImage(url: string) { activeImage.value = url; activeImageError.value = false }
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
