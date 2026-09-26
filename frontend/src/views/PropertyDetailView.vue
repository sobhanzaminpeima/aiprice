<template>
  <div v-if="loading" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <div class="w-12 h-12 rounded-full border-2 animate-spin mx-auto mb-4" style="border-color:rgba(111,255,210,0.2);border-top-color:var(--accent-teal)"></div>
      <p style="color:var(--text-muted)">Loading property...</p>
    </div>
  </div>

  <div v-else-if="!listing" class="min-h-screen flex items-center justify-center">
    <div class="text-center">
      <AppIcon name="home" :size="64" class="mx-auto mb-4" />
      <h2 class="text-xl font-bold mb-2" style="color:var(--text-primary)">Property Not Found</h2>
      <RouterLink to="/properties" class="btn-primary mt-4 inline-block">Browse Properties</RouterLink>
    </div>
  </div>

  <div v-else>
    <!-- Hero gallery -->
    <div class="relative" style="height:500px;background:var(--bg-surface)">
      <img v-if="activeImage && !imgErr" :src="activeImage" :alt="listing.title" class="w-full h-full object-cover" @error="imgErr = true" />
      <div v-else class="w-full h-full flex items-center justify-center opacity-20"><AppIcon name="home" :size="112" /></div>
      <div class="absolute inset-0" style="background:linear-gradient(to bottom,rgba(5,7,11,0.15) 0%,transparent 35%,rgba(5,7,11,0.85) 100%)"></div>

      <!-- Thumbnails -->
      <div v-if="images.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
        <button v-for="(img, i) in images" :key="i" @click="setImg(img)" :aria-label="`Show photo ${Number(i) + 1}`" class="w-16 h-11 rounded-xl overflow-hidden border-2 transition" :style="activeImage === img ? 'border-color:var(--accent-teal)' : 'border-color:rgba(255,255,255,0.2)'">
          <img :src="img" :alt="`${listing.title} — photo ${Number(i) + 1}`" loading="lazy" class="w-full h-full object-cover" />
        </button>
      </div>

      <RouterLink to="/properties" class="absolute top-5 left-5 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium" style="background:rgba(0,0,0,0.5);color:var(--text-primary);backdrop-filter:blur(8px)">
        ← Properties
      </RouterLink>

      <!-- Quick info overlay bottom-left -->
      <div class="absolute bottom-8 left-8">
        <h1 class="text-3xl font-black mb-2 drop-shadow-xl" style="color:#fff">{{ listing.title }}</h1>
        <div class="flex items-center gap-3 text-sm" style="color:rgba(255,255,255,0.8)">
          <span class="flex items-center gap-1"><AppIcon name="mapPin" :size="14" /> {{ listing.city }}, North Cyprus</span>
          <span v-if="property?.bedrooms">· {{ property.bedrooms }} bed</span>
          <span v-if="property?.bathrooms">· {{ property.bathrooms }} bath</span>
          <span v-if="property?.area_sqm">· {{ property.area_sqm }}m²</span>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left main -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Key facts ribbon -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div v-for="f in keyFacts" :key="f.label" class="rounded-xl p-4 text-center" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
              <AppIcon :name="f.icon" :size="24" class="mx-auto mb-1" />
              <div class="text-base font-black" style="color:var(--text-primary)">{{ f.value }}</div>
              <div class="text-xs mt-0.5" style="color:var(--text-muted)">{{ f.label }}</div>
            </div>
          </div>

          <!-- Property details -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5 flex items-center gap-2" style="color:var(--text-primary)">
              <AppIcon name="home" :size="20" style="color:var(--accent-cyan)" /> Property Details
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
              <SpecItem v-if="property?.bedrooms" icon="bed" label="Bedrooms" :value="String(property.bedrooms)" />
              <SpecItem v-if="property?.bathrooms" icon="bath" label="Bathrooms" :value="String(property.bathrooms)" />
              <SpecItem v-if="property?.area_sqm" icon="ruler" label="Area (m²)" :value="String(property.area_sqm) + ' m²'" />
              <SpecItem v-if="property?.area_sqft" icon="ruler" label="Area (ft²)" :value="String(property.area_sqft) + ' ft²'" />
              <SpecItem v-if="property?.furnishing" icon="home" label="Furnishing" :value="capitalize(property.furnishing)" />
              <SpecItem v-if="property?.condition" icon="sparkles" label="Condition" :value="capitalize(property.condition)" />
              <SpecItem v-if="property?.floor_number != null" icon="building" label="Floor" :value="property.floor_number === 0 ? 'Ground' : String(property.floor_number)" />
              <SpecItem v-if="property?.build_year" icon="calendar" label="Built" :value="String(property.build_year)" />
              <SpecItem icon="mapPin" label="Location" :value="listing.city" />
            </div>

            <!-- Amenities -->
            <div class="mt-6 pt-5" style="border-top:1px solid var(--border-subtle)">
              <h3 class="text-sm font-semibold mb-3" style="color:var(--text-secondary)">Amenities</h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <div v-for="a in amenitiesList" :key="a.label" class="flex items-center gap-2 text-xs py-2 px-3 rounded-lg" :style="a.has ? 'background:rgba(111,255,210,0.06);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)' : 'background:var(--surface-overlay-soft);color:var(--text-muted);border:1px solid var(--surface-overlay)'">
                  <span>{{ a.has ? '✓' : '✗' }}</span> {{ a.label }}
                </div>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4" style="color:var(--text-primary)">About This Property</h2>
            <p class="text-sm leading-relaxed whitespace-pre-line" style="color:var(--text-secondary)">{{ listing.description }}</p>
          </div>

          <!-- Location info -->
          <div class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-4 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="mapPin" :size="20" /> Location</h2>
            <div class="grid grid-cols-3 gap-4">
              <div v-for="loc in nearbyLocations" :key="loc.label" class="rounded-xl p-3 text-center" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
                <AppIcon :name="loc.icon" :size="20" class="mx-auto mb-1" />
                <div class="text-sm font-bold" style="color:var(--text-primary)">{{ loc.dist }}</div>
                <div class="text-xs" style="color:var(--text-muted)">{{ loc.label }}</div>
              </div>
            </div>
          </div>

          <!-- AI Investment Analysis -->
          <div class="rounded-2xl p-6" style="background:linear-gradient(135deg,rgba(86,216,255,0.04),rgba(167,139,250,0.03));border:1px solid rgba(86,216,255,0.15)">
            <div class="flex items-center gap-3 mb-5">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs" style="background:linear-gradient(135deg,#56d8ff,#a78bfa);color:var(--on-accent)">AI</div>
              <h2 class="font-bold text-lg" style="color:var(--text-primary)">AI Investment Analysis</h2>
            </div>

            <div v-if="listing.market_min_price && listing.market_max_price" class="mb-6">
              <div class="flex justify-between text-xs mb-2" style="color:var(--text-muted)">
                <span>Min: {{ formatPrice(listing.market_min_price, listing.currency) }}</span>
                <span>Max: {{ formatPrice(listing.market_max_price, listing.currency) }}</span>
              </div>
              <div class="relative h-3 rounded-full" style="background:var(--border-subtle)">
                <div class="h-3 rounded-full" style="background:linear-gradient(90deg,rgba(86,216,255,0.3),rgba(167,139,250,0.3));width:100%"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full border-2" style="background:#56d8ff;border-color:var(--on-accent);box-shadow:0 0 12px rgba(86,216,255,0.6)" :style="{ left: pricePosition + '%', transform: 'translateX(-50%) translateY(-50%)' }"></div>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <ScoreGauge v-if="listing.ai_trust_score" label="Trust Score" :value="listing.ai_trust_score" color="#6fffd2" />
              <ScoreGauge v-if="listing.ai_investment_score" label="ROI Score" :value="listing.ai_investment_score" color="#a78bfa" />
              <ScoreGauge v-if="listing.ai_confidence_score" label="Confidence" :value="listing.ai_confidence_score" color="#56d8ff" />
            </div>

            <div v-if="listing.ai_analysis" class="mt-5 p-4 rounded-xl text-sm leading-relaxed" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle);color:var(--text-secondary)">
              <strong style="color:var(--accent-cyan)">AI Analysis:</strong> {{ listing.ai_analysis }}
            </div>
          </div>

          <!-- Why this score? + Show Me Better -->
          <AiExplainability :listing="listing" />

          <!-- Similar properties -->
          <div v-if="comparables.length" class="rounded-2xl p-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h2 class="font-bold text-lg mb-5" style="color:var(--text-primary)">Similar Properties</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <RouterLink v-for="c in comparables" :key="c.id" :to="`/listings/${c.slug}`" class="flex items-center gap-3 p-3 rounded-xl transition-all" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)" @mouseenter="(e:any) => e.currentTarget.style.borderColor='rgba(86,216,255,0.2)'" @mouseleave="(e:any) => e.currentTarget.style.borderColor='var(--border-subtle)'">
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
          <div class="rounded-2xl p-6 sticky top-20" style="background:var(--bg-card);border:1px solid rgba(86,216,255,0.2)">
            <p class="text-3xl font-black mb-0.5" style="color:var(--text-primary)">{{ formatPrice(listing.price, listing.currency) }}</p>
            <p v-if="property?.area_sqm" class="text-sm mb-4" style="color:var(--text-muted)">{{ formatPrice(listing.price / property.area_sqm, listing.currency) }}/m²</p>
            <p v-if="listing.price_negotiable" class="text-sm mb-4" style="color:var(--accent-cyan)">✓ Price negotiable</p>

            <!-- Battery -->
            <div v-if="listing.price_battery_percent" class="mb-5 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div class="flex justify-between text-xs mb-2">
                <span style="color:var(--text-muted)">Investment Score</span>
                <span :style="`color:${batteryColor};font-weight:700`">{{ listing.price_battery_percent }}%</span>
              </div>
              <div class="h-2 rounded-full" style="background:var(--border-subtle)">
                <div class="h-2 rounded-full" :style="`width:${listing.price_battery_percent}%;background:${batteryColor}`"></div>
              </div>
            </div>

            <!-- Seller: identity is never shown — all contact goes through AIPrice -->
            <div class="flex items-center gap-3 mb-5 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold" style="background:linear-gradient(135deg,rgba(86,216,255,0.2),rgba(167,139,250,0.2));color:var(--accent-cyan)">
                <AppIcon name="shield" :size="20" />
              </div>
              <div>
                <p class="text-sm font-semibold" style="color:var(--text-primary)">Verified Listing</p>
                <p class="text-xs" style="color:var(--text-muted)">Contact is handled by the AIPrice team</p>
              </div>
            </div>

            <div class="space-y-3">
              <button @click="showInquiry = !showInquiry" class="w-full py-3.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2" style="background:linear-gradient(135deg,#56d8ff,#a78bfa);color:var(--on-accent)">
                <AppIcon name="mail" :size="18" /> Request Viewing
              </button>
              <button @click="toggleFavorite" class="w-full py-3.5 rounded-xl font-semibold text-sm flex items-center justify-center gap-2" :style="isFavorited ? 'background:rgba(239,68,68,0.12);color:#ef4444;border:1px solid rgba(239,68,68,0.25)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-strong)'">
                <AppIcon name="heart" :size="18" :fill="isFavorited ? 'currentColor' : 'none'" /> {{ isFavorited ? 'Saved' : 'Save Property' }}
              </button>
            </div>

            <Transition name="slide-down">
              <div v-if="showInquiry" class="mt-4 pt-4" style="border-top:1px solid var(--border-subtle)">
                <textarea v-model="inquiryMsg" rows="3" placeholder="I am interested in viewing this property..." class="w-full rounded-xl p-3 text-sm resize-none outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
                <button @click="sendInquiry" :disabled="sendingInquiry" class="w-full mt-2 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#56d8ff,#a78bfa);color:var(--on-accent)">
                  {{ sendingInquiry ? 'Sending...' : 'Send Request' }}
                </button>
                <p v-if="inquirySent" class="text-xs text-center mt-2" style="color:var(--accent-teal)">✓ Request sent!</p>
                <p v-if="inquiryError" class="text-xs text-center mt-2" style="color:#ef4444">{{ inquiryError }}</p>
              </div>
            </Transition>
          </div>

          <!-- Why buy in NC -->
          <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <h3 class="text-sm font-bold mb-3 flex items-center gap-2" style="color:var(--text-primary)"><AppIcon name="star" :size="18" /> Why North Cyprus?</h3>
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
import AppIcon from '@/components/ui/AppIcon.vue'
import ScoreGauge from '@/components/listings/ScoreGauge.vue'
import { apiErrorMessage } from '@/api/errors'
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useListingsStore } from '@/stores/listings'
import AiExplainability from '@/components/listings/AiExplainability.vue'
import type { Listing, Property } from '@/types'


const route = useRoute()
const store = useListingsStore()
const loading = ref(true)
const listing = ref<Listing | null>(null)
const comparables = ref<any[]>([])
const isFavorited = ref(false)
const activeImage = ref<string | null>(null)
const imgErr = ref(false)
const showInquiry = ref(false)
const inquiryMsg = ref('')
const sendingInquiry = ref(false)
const inquirySent = ref(false)
const inquiryError = ref('')

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
    { label: 'Air Con', has: true },
    { label: 'Security', has: true },
  ]
})

const nearbyLocations = computed(() => {
  const nearby = (property.value as any)?.nearby_locations ?? {}
  return [
    { icon: 'plane', label: 'Airport', dist: nearby.airport ?? '~30km' },
    { icon: 'mapPin', label: 'Beach', dist: nearby.beach ?? '~5km' },
    { icon: 'building', label: 'City Centre', dist: nearby.city_centre ?? '~3km' },
  ]
})

const pricePosition = computed(() => {
  const l = listing.value
  if (!l?.market_min_price || !l?.market_max_price) return 50
  const range = l.market_max_price - l.market_min_price
  if (range <= 0) return 50
  return Math.max(2, Math.min(98, ((l.price - l.market_min_price) / range) * 100))
})

const batteryColor = computed(() => { const p = listing.value?.price_battery_percent ?? 0; return p >= 85 ? '#6fffd2' : p >= 70 ? '#56d8ff' : '#fbbf24' })

function setImg(url: string) { activeImage.value = url; imgErr.value = false }
function capitalize(s: string) { return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : '' }
function formatPrice(val: number, currency = 'GBP') {
  try { return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', maximumFractionDigits: 0 }).format(val) }
  catch { return `${currency} ${Number(val)?.toLocaleString()}` }
}

async function toggleFavorite() { try { await store.toggleFavorite(listing.value!.id); isFavorited.value = !isFavorited.value } catch {} }
async function sendInquiry() {
  if (!inquiryMsg.value.trim()) return
  sendingInquiry.value = true
  inquiryError.value = ''
  try { await store.sendInquiry(listing.value!.id, inquiryMsg.value); inquirySent.value = true; inquiryMsg.value = ''; setTimeout(() => { showInquiry.value = false; inquirySent.value = false }, 3000) }
  catch (e) { inquiryError.value = apiErrorMessage(e, 'Could not send your message.') } finally { sendingInquiry.value = false }
}

const whyNC = [
  'No capital gains tax or inheritance tax',
  'Strong rental yields of 6-12% per annum',
  'Growing expat community and tourism sector',
  'Affordable prices vs EU/UK property markets',
  'Mediterranean climate year-round',
]

async function load() {
  loading.value = true
  try {
    const res = await store.fetchListing(route.params.slug as string) as any
    listing.value = res.listing ?? res
    comparables.value = res.comparable_listings ?? []
    isFavorited.value = res.is_favorited ?? false
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
