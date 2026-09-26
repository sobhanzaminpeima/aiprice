<template>
  <div v-if="loading" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <div class="w-12 h-12 rounded-full border-2 border-t-[#6fffd2] animate-spin mx-auto mb-4" style="border-color:rgba(111,255,210,0.2);border-top-color:var(--accent-teal)"></div>
      <p style="color:var(--text-muted)">Loading listing...</p>
    </div>
  </div>

  <div v-else-if="!listing" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <AppIcon name="car" :size="64" class="mx-auto mb-4" />
      <h2 class="text-xl font-bold mb-2" style="color:var(--text-primary)">Listing Not Found</h2>
      <RouterLink to="/cars" class="btn-primary mt-4 inline-block">Browse Cars</RouterLink>
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
        ← Back to Cars
      </RouterLink>

      <!-- Featured badge -->
      <div v-if="listing.is_featured" class="absolute top-5 right-5 ai-badge flex items-center gap-1"><AppIcon name="star" :size="14" /> Featured</div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left: Main info -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Title + quick specs -->
          <div>
            <div class="flex flex-wrap items-start gap-3 mb-3">
              <h1 class="text-3xl font-black" style="color:var(--text-primary)">{{ listing.title }}</h1>
              <span v-if="listing.ai_price_status" class="px-3 py-1 rounded-full text-sm font-bold mt-1" :style="aiStatusStyle">{{ aiStatusLabel }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm" style="color:var(--text-muted)">
              <span class="flex items-center gap-1"><AppIcon name="mapPin" :size="14" /> {{ listing.city }}, North Cyprus</span>
              <span>·</span>
              <span>Listed {{ listedAgo }}</span>
              <span>·</span>
              <span class="flex items-center gap-1"><AppIcon name="eye" :size="14" /> {{ listing.view_count || 0 }} views</span>
              <span v-if="listing.listing_number" class="ml-auto text-xs font-mono px-2 py-0.5 rounded" style="background:var(--surface-overlay);color:var(--text-muted)">{{ listing.listing_number }}</span>
            </div>
          </div>

          <!-- Vehicle specs grid -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5 flex items-center gap-2" style="color:var(--text-primary)">
              <AppIcon name="car" :size="20" style="color:var(--accent-teal)" /> Vehicle Specifications
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <SpecItem v-if="vehicle?.year" icon="calendar" label="Year" :value="String(vehicle.year)" />
              <SpecItem v-if="vehicle?.mileage" icon="gauge" label="Mileage" :value="Number(vehicle.mileage).toLocaleString() + ' km'" />
              <SpecItem v-if="vehicle?.fuel_type" icon="fuel" label="Fuel" :value="capitalize(vehicle.fuel_type)" />
              <SpecItem v-if="vehicle?.transmission" icon="settings" label="Transmission" :value="capitalize(vehicle.transmission)" />
              <SpecItem v-if="vehicle?.body_type" icon="car" label="Body Type" :value="capitalize(vehicle.body_type)" />
              <SpecItem v-if="vehicle?.color" icon="palette" label="Color" :value="vehicle.color" />
              <SpecItem v-if="vehicle?.engine_size" icon="wrench" label="Engine" :value="vehicle.engine_size + 'L'" />
              <SpecItem v-if="vehicle?.condition" icon="check" label="Condition" :value="capitalize(vehicle.condition)" />
              <SpecItem icon="globe" label="Location" :value="listing.city" />
            </div>

            <!-- Features -->
            <div v-if="vehicleFeatures.length" class="mt-6 pt-5" style="border-top:1px solid var(--border-subtle)">
              <h3 class="text-sm font-semibold mb-3" style="color:var(--text-secondary)">Features & Equipment</h3>
              <div class="flex flex-wrap gap-2">
                <span v-for="f in vehicleFeatures" :key="f" class="px-2.5 py-1 rounded-lg text-xs font-medium" style="background:rgba(111,255,210,0.07);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)">
                  ✓ {{ formatFeature(f) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4" style="color:var(--text-primary)">Description</h2>
            <p class="text-sm leading-relaxed whitespace-pre-line" style="color:var(--text-secondary)">{{ listing.description }}</p>
          </div>

          <!-- AI Price Analysis -->
          <div class="rounded-2xl p-6" style="background:linear-gradient(135deg,rgba(111,255,210,0.04),rgba(86,216,255,0.02));border:1px solid rgba(111,255,210,0.15)">
            <div class="flex items-center gap-3 mb-5">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">AI</div>
              <h2 class="font-bold text-lg" style="color:var(--text-primary)">AI Price Intelligence</h2>
            </div>

            <!-- Price range bar -->
            <div v-if="listing.market_min_price && listing.market_max_price" class="mb-6">
              <div class="flex items-center justify-between text-xs mb-2" style="color:var(--text-muted)">
                <span>Market Min: {{ formatPrice(listing.market_min_price, listing.currency) }}</span>
                <span>Market Max: {{ formatPrice(listing.market_max_price, listing.currency) }}</span>
              </div>
              <div class="relative h-3 rounded-full" style="background:var(--border-subtle)">
                <div class="absolute inset-y-0 rounded-full" style="background:linear-gradient(90deg,rgba(111,255,210,0.3),rgba(86,216,255,0.3))" :style="{ left: '0%', right: '0%' }"></div>
                <!-- Price marker -->
                <div class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full border-2 shadow-lg" style="background:#6fffd2;border-color:var(--on-accent);box-shadow:0 0 12px rgba(111,255,210,0.6)" :style="{ left: pricePosition + '%', transform: 'translateX(-50%) translateY(-50%)' }"></div>
              </div>
              <p class="text-xs mt-2 text-center" style="color:var(--text-muted)">
                This vehicle is priced at <span :style="`color:${aiColor};font-weight:700`">{{ formatPrice(listing.price, listing.currency) }}</span>
                <template v-if="listing.market_avg_price"> vs market avg <span style="color:var(--text-secondary);font-weight:600">{{ formatPrice(listing.market_avg_price, listing.currency) }}</span></template>
              </p>
            </div>

            <!-- Score grid -->
            <div class="grid grid-cols-3 gap-4">
              <ScoreGauge v-if="listing.ai_trust_score" label="Trust Score" :value="listing.ai_trust_score" :color="trustColor" />
              <ScoreGauge v-if="listing.ai_investment_score" label="Investment" :value="listing.ai_investment_score" color="#a78bfa" />
              <ScoreGauge v-if="listing.ai_confidence_score" label="Confidence" :value="listing.ai_confidence_score" color="#56d8ff" />
            </div>
          </div>

          <!-- Why this score? + Show Me Better -->
          <AiExplainability :listing="listing" />

          <!-- Comparable listings -->
          <div v-if="comparables.length" class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5" style="color:var(--text-primary)">Similar Vehicles</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <RouterLink v-for="c in comparables" :key="c.id" :to="`/listings/${c.slug}`" class="flex items-center gap-3 p-3 rounded-xl transition-all" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)" @mouseenter="(e:any) => e.currentTarget.style.borderColor='rgba(111,255,210,0.2)'" @mouseleave="(e:any) => e.currentTarget.style.borderColor='var(--border-subtle)'">
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
          <div class="rounded-2xl p-6 sticky top-20" style="background:var(--bg-card);border:1px solid rgba(111,255,210,0.2)">
            <div class="mb-4">
              <p class="text-3xl font-black mb-1" style="color:var(--text-primary)">{{ formatPrice(listing.price, listing.currency) }}</p>
              <p v-if="listing.price_negotiable" class="text-sm" style="color:var(--accent-teal)">✓ Price is negotiable</p>
            </div>

            <!-- Battery -->
            <div v-if="listing.price_battery_percent" class="mb-5 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div class="flex items-center justify-between text-xs mb-2">
                <span style="color:var(--text-muted)">Price Fairness Score</span>
                <span :style="`color:${batteryColor};font-weight:700`">{{ listing.price_battery_percent }}%</span>
              </div>
              <div class="h-2 rounded-full" style="background:var(--border-subtle)">
                <div class="h-2 rounded-full transition-all" :style="`width:${listing.price_battery_percent}%;background:${batteryColor}`"></div>
              </div>
            </div>

            <!-- Seller info: identity is never shown — all contact goes through AIPrice -->
            <div class="flex items-center gap-3 mb-5 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold" style="background:linear-gradient(135deg,rgba(111,255,210,0.2),rgba(86,216,255,0.2));color:var(--accent-teal)">
                <AppIcon name="shield" :size="20" />
              </div>
              <div>
                <p class="text-sm font-semibold" style="color:var(--text-primary)">Verified Listing</p>
                <p class="text-xs" style="color:var(--text-muted)">Contact is handled by the AIPrice team</p>
              </div>
            </div>

            <!-- CTA buttons -->
            <div class="space-y-3">
              <button @click="showInquiry = !showInquiry" class="w-full py-3.5 rounded-xl font-bold text-sm transition-all flex items-center justify-center gap-2" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
                <AppIcon name="mail" :size="18" /> Send Inquiry
              </button>
              <button @click="toggleFavorite" class="w-full py-3.5 rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2" :style="isFavorited ? 'background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.25)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-strong)'">
                <AppIcon name="heart" :size="18" :fill="isFavorited ? 'currentColor' : 'none'" /> {{ isFavorited ? 'Saved' : 'Save Listing' }}
              </button>
            </div>

            <!-- Inquiry form -->
            <Transition name="slide-down">
              <div v-if="showInquiry" class="mt-4 pt-4" style="border-top:1px solid var(--border-subtle)">
                <textarea v-model="inquiryMsg" rows="3" placeholder="Hello, I am interested in this vehicle..." class="w-full rounded-xl p-3 text-sm resize-none outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
                <button @click="sendInquiry" :disabled="sendingInquiry" class="w-full mt-2 py-2.5 rounded-xl text-sm font-bold transition" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)" :class="{'opacity-60':sendingInquiry}">
                  {{ sendingInquiry ? 'Sending...' : 'Send Message' }}
                </button>
                <p v-if="inquirySent" class="text-xs text-center mt-2" style="color:var(--accent-teal)">✓ Message sent successfully!</p>
                <p v-if="inquiryError" class="text-xs text-center mt-2" style="color:#ef4444">{{ inquiryError }}</p>
              </div>
            </Transition>
          </div>

          <!-- Safety tips -->
          <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="text-sm font-bold mb-3 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="shield" :size="18" /> Safety Tips</h3>
            <ul class="space-y-2">
              <li v-for="tip in safetyTips" :key="tip" class="text-xs" style="color:var(--text-muted)">• {{ tip }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import SpecItem from '@/components/listings/SpecItem.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ScoreGauge from '@/components/listings/ScoreGauge.vue'
import { apiErrorMessage } from '@/api/errors'
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
const showInquiry = ref(false)
const inquiryMsg = ref('')
const sendingInquiry = ref(false)
const inquirySent = ref(false)
const inquiryError = ref('')

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
  if (s === 'fair') return 'background:rgba(111,255,210,0.12);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.3)'
  return 'background:rgba(251,191,36,0.12);color:#fbbf24;border:1px solid rgba(251,191,36,0.3)'
})
const aiStatusLabel = computed(() => {
  const map: Record<string, string> = { fair: '✓ Fair Price', slightly_overpriced: '⚠ Slightly Overpriced', overpriced: '✗ Overpriced' }
  return map[listing.value?.ai_price_status ?? ''] ?? ''
})
const aiColor = computed(() => listing.value?.ai_price_status === 'fair' ? '#6fffd2' : '#fbbf24')
const trustColor = computed(() => {
  const s = listing.value?.ai_trust_score ?? 0
  return s >= 80 ? '#6fffd2' : s >= 60 ? '#56d8ff' : s >= 40 ? '#fbbf24' : '#ef4444'
})
const batteryColor = computed(() => {
  const p = listing.value?.price_battery_percent ?? 0
  return p >= 85 ? '#6fffd2' : p >= 70 ? '#56d8ff' : p >= 50 ? '#fbbf24' : '#ef4444'
})

const listedAgo = computed(() => {
  if (!listing.value?.published_at) return 'recently'
  const diff = Date.now() - new Date(listing.value.published_at).getTime()
  const days = Math.floor(diff / 86400000)
  return days === 0 ? 'today' : days === 1 ? 'yesterday' : `${days} days ago`
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

async function sendInquiry() {
  if (!inquiryMsg.value.trim()) return
  sendingInquiry.value = true
  inquiryError.value = ''
  try {
    await store.sendInquiry(listing.value!.id, inquiryMsg.value)
    inquirySent.value = true; inquiryMsg.value = ''
    setTimeout(() => { showInquiry.value = false; inquirySent.value = false }, 3000)
  } catch (e) { inquiryError.value = apiErrorMessage(e, 'Could not send your message.') } finally { sendingInquiry.value = false }
}

const safetyTips = [
  'Always inspect the vehicle in person before payment',
  'Check title deed and registration documents carefully',
  'Use our AI Trust Score to assess listing authenticity',
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
