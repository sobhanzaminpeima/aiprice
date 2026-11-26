<template>
  <RouterLink :to="`/listings/${listing.slug}`" class="block group h-full">
    <article class="card h-full flex flex-col rounded-2xl overflow-hidden transition-all duration-300 group-hover:-translate-y-1">
      <!-- Photo with the AI verdict -->
      <div class="relative overflow-hidden" style="height:180px;background:var(--bg-elevated)">
        <img v-if="primaryImage" :src="primaryImage" :alt="listing.title" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" @error="imgError = true" />
        <div v-else class="absolute inset-0 flex items-center justify-center opacity-30"><AssetIcon :type="(listing.type || 'vehicle') as any" :size="56" /></div>

        <div class="absolute top-3 left-3 flex gap-1.5 flex-wrap">
          <span v-if="match != null" class="verdict" style="background:#e3f8ee;color:#046c4e">✦ {{ $t('AI match {n}%', { n: match }) }}</span>
          <span v-if="verdict" class="verdict" :style="verdict.style">{{ verdict.icon }} {{ $t(verdict.label) }}</span>
          <span v-if="listing.is_featured" class="verdict" style="background:#fff7e0;color:#8a5a00">★ {{ $t('Featured') }}</span>
        </div>
        <!-- Price battery: how good the price is against similar listings -->
        <BatteryMeter :percent="hasRange ? battery : null" class="absolute bottom-3 left-3" />
        <span v-if="listing.type" class="absolute top-3 right-3 type-chip">{{ $t(listing.type === 'vehicle' ? 'Car' : cap(listing.type)) }}</span>
      </div>

      <!-- Details -->
      <div class="p-4 flex flex-col flex-1">
        <h3 class="font-bold text-sm leading-snug mb-1 line-clamp-1" style="color:var(--text-primary)">{{ listing.title }}</h3>
        <p class="text-xs mb-2 flex items-center gap-1.5" style="color:var(--text-muted)">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          {{ listing.city || '—' }}, North Cyprus
        </p>
        <p class="text-xl font-black mb-2" style="color:var(--text-primary)">{{ formatPrice(listing.price, listing.currency) }}<span v-if="listing.price_negotiable" class="text-[11px] font-semibold ml-1.5" style="color:var(--text-muted)">{{ $t('Negotiable') }}</span></p>

        <div class="flex flex-wrap gap-1.5 mb-3">
          <template v-if="listing.type === 'vehicle' && vehicle">
            <span v-if="vehicle.year" class="chip">{{ vehicle.year }}</span>
            <span v-if="vehicle.mileage" class="chip">{{ Number(vehicle.mileage).toLocaleString() }} km</span>
            <span v-if="vehicle.fuel_type" class="chip">{{ $t(cap(vehicle.fuel_type)) }}</span>
            <span v-if="vehicle.transmission" class="chip">{{ $t(cap(vehicle.transmission)) }}</span>
          </template>
          <template v-else-if="property">
            <span v-if="property.bedrooms" class="chip">{{ property.bedrooms }} {{ $t('bed') }}</span>
            <span v-if="property.bathrooms" class="chip">{{ property.bathrooms }} {{ $t('bath') }}</span>
            <span v-if="property.area_sqm" class="chip">{{ property.area_sqm }} m²</span>
          </template>
        </div>

        <!-- AI fair range + confidence -->
        <div class="mt-auto pt-3 flex items-center gap-2" style="border-top:1px solid var(--border-subtle)">
          <span class="ai-mark">AI</span>
          <template v-if="hasRange">
            <span class="text-[11px] leading-tight flex-1 min-w-0" style="color:var(--text-muted)">{{ $t('AI fair range') }}: <strong class="whitespace-nowrap" style="color:var(--text-primary)">{{ rangeText }}</strong></span>
            <span v-if="listing.ai_confidence_score" class="text-right shrink-0 leading-tight"><strong class="block text-sm" style="color:var(--accent-teal)">{{ Math.round(Number(listing.ai_confidence_score)) }}%</strong><span class="text-[10px]" style="color:var(--text-muted)">{{ $t('Confidence') }}</span></span>
          </template>
          <span v-else class="text-[11px] flex-1" style="color:var(--text-muted)" :title="$t('We compare each price with similar listings. There are not enough yet — the price check switches on as more listings are added.')">{{ aiStatusLabel }}</span>
        </div>
        <div class="flex items-center justify-between mt-2 text-[11px]" style="color:var(--text-muted)">
          <PostedAt :listing="listing" />
          <span class="flex items-center gap-1"><svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>{{ listing.view_count || 0 }}</span>
        </div>
      </div>
    </article>
  </RouterLink>
</template>

<script setup lang="ts">
import PostedAt from '@/components/listings/PostedAt.vue'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AssetIcon from '@/components/ui/AssetIcon.vue'
import BatteryMeter from '@/components/listings/BatteryMeter.vue'
import type { Listing, Vehicle, Property } from '@/types'
import { t } from '@/i18n'

const props = defineProps<{ listing: Listing; match?: number | null }>()
const imgError = ref(false)

const vehicle = computed((): Vehicle | null =>
  props.listing.type === 'vehicle' ? props.listing.listable as Vehicle : null
)
const property = computed((): Property | null =>
  props.listing.type !== 'vehicle' ? props.listing.listable as Property : null
)

const primaryImage = computed((): string | null => {
  if (imgError.value) return null
  const imgs = props.listing.images
  if (imgs && imgs.length > 0) return imgs[0] ?? null
  return props.listing.thumbnail ?? null
})

// Battery (price score 0-100)
const battery = computed((): number | null => {
  const v = props.listing.price_battery_percent ?? props.listing.ai_price_score
  return v != null ? Math.round(Number(v)) : null
})





// Label from the stored verdict, never from a default score: a listing
// without enough comparables must not read as "slightly high".
const rangeText = computed(() => {
  const l = props.listing
  const f = (v: number) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: l.currency || 'GBP', maximumFractionDigits: 0 }).format(v)
  return `${f(Number(l.market_min_price))} – ${f(Number(l.market_max_price))}`
})
const hasRange = computed(() => !!props.listing.market_min_price && !!props.listing.market_max_price && !!props.listing.ai_price_status && props.listing.ai_price_status !== 'insufficient_data')
// The verdict badge on the photo, from the stored price check only.
const verdict = computed(() => {
  const l = props.listing
  if (!hasRange.value) return null
  const viaAi = (l as { ai_score_breakdown?: { price_source?: string } }).ai_score_breakdown?.price_source === 'ai_model'
  if (viaAi) return { icon: '✦', label: 'AI estimate', style: 'background:#eaf3ff;color:#0b5cad' }
  if (Number(l.price) < Number(l.market_min_price)) return { icon: '↓', label: 'Undervalued', style: 'background:#efe7ff;color:#5b2bb5' }
  if (l.ai_price_status === 'overpriced') return { icon: '▲', label: 'High price', style: 'background:#ffe9e9;color:#a11d1d' }
  if (l.ai_price_status === 'slightly_overpriced') return { icon: '▲', label: 'Slightly high', style: 'background:#fff3dd;color:#8a4b00' }
  if ((battery.value ?? 0) >= 90) return { icon: '✓', label: 'Good deal', style: 'background:#e3f8ee;color:#046c4e' }
  return { icon: '✓', label: 'Fair price', style: 'background:#e6f1ff;color:#0b5cad' }
})
const aiStatusLabel = computed((): string => {
  const status = props.listing.ai_price_status
  const b = battery.value
  if (!status || status === 'insufficient_data' || b === null) return t('Waiting for similar listings')
  const ai = (props.listing as { ai_score_breakdown?: { price_source?: string } }).ai_score_breakdown?.price_source === 'ai_model'
  if (ai) return '✦ ' + t('AI rough estimate')
  if (status === 'overpriced') return '▲ ' + t('High for this market')
  if (status === 'slightly_overpriced') return '▲ ' + t('Slightly high')
  return b >= 90 ? '✦ ' + t('Good price (estimate)') : '~ ' + t('Fair price (estimate)')
})
const cap = (v: string) => (v ? v.charAt(0).toUpperCase() + v.slice(1).toLowerCase() : v)

function formatPrice(val: number, currency = 'GBP'): string {
  try {
    return new Intl.NumberFormat('en-GB', {
      style: 'currency',
      currency: currency || 'GBP',
      maximumFractionDigits: 0,
    }).format(val)
  } catch {
    return `${currency} ${Number(val)?.toLocaleString()}`
  }
}
</script>

<style scoped>
.card { background: var(--bg-card); border: 1px solid var(--border-subtle); box-shadow: 0 1px 2px rgba(8,24,39,.04), 0 10px 28px rgba(8,24,39,.07); }
.group:hover .card { border-color: rgba(var(--accent-cyan-rgb), .35); box-shadow: 0 16px 40px rgba(8,24,39,.12); }
.verdict { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:800;box-shadow:0 2px 8px rgba(0,0,0,.15) }
.type-chip { padding:3px 8px;border-radius:8px;font-size:11px;font-weight:700;color:#fff;background:rgba(8,24,39,.65);backdrop-filter:blur(4px) }
.ai-mark { flex-shrink:0;width:24px;height:24px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;color:#0b5cad;background:rgba(var(--accent-cyan-rgb),.12) }
.chip {
  background: var(--surface-overlay);
  color: var(--text-secondary);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 2px 8px;
  font-size: 11px;
  white-space: nowrap;
}
</style>
