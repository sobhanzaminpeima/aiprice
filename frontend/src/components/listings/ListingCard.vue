<template>
  <RouterLink :to="`/listings/${listing.slug}`" class="block group">
    <div class="rounded-2xl overflow-hidden transition-all duration-300 group-hover:-translate-y-1.5"
      style="background:var(--bg-surface);border:1px solid var(--border-subtle)"
      @mouseenter="(e:MouseEvent) => (e.currentTarget as HTMLElement).style.borderColor='rgba(111,255,210,0.25)'"
      @mouseleave="(e:MouseEvent) => (e.currentTarget as HTMLElement).style.borderColor='var(--border-subtle)'">

      <!-- Image -->
      <div class="relative overflow-hidden" style="height:196px;background:linear-gradient(135deg,#0d1a26,#0a1520)">
        <img v-if="primaryImage" :src="primaryImage" :alt="listing.title"
          class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          loading="lazy" @error="imgError = true" />
        <div v-else class="absolute inset-0 flex items-center justify-center">
          <svg v-if="listing.type === 'vehicle'" width="60" height="60" fill="none" viewBox="0 0 24 24" stroke="rgba(111,255,210,0.15)" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/>
          </svg>
          <svg v-else-if="listing.type === 'property'" width="60" height="60" fill="none" viewBox="0 0 24 24" stroke="rgba(167,139,250,0.15)" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <svg v-else width="60" height="60" fill="none" viewBox="0 0 24 24" stroke="rgba(86,216,255,0.15)" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
          </svg>
        </div>

        <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(5,7,11,0.9) 0%,transparent 55%)"></div>

        <!-- Badges top-left -->
        <div class="absolute top-3 left-3 flex gap-1.5 flex-wrap">
          <span v-if="listing.is_featured" class="flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-semibold" style="background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.3)">
            <svg width="9" height="9" fill="#fbbf24" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            Featured
          </span>
          <span v-if="listing.is_verified" class="flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-semibold" style="background:rgba(111,255,210,0.12);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.25)">
            <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Verified
          </span>
        </div>

        <!-- Price bottom-left -->
        <div class="absolute bottom-3 left-3">
          <p class="text-xl font-black drop-shadow-lg" style="color:#fff">{{ formatPrice(listing.price, listing.currency) }}</p>
          <p v-if="listing.price_negotiable" class="text-xs" style="color:rgba(255,255,255,0.7)">Negotiable</p>
        </div>

        <!-- Battery bottom-right -->
        <div class="absolute bottom-3 right-3 flex flex-col items-end gap-1">
          <span class="text-xs px-2 py-0.5 rounded capitalize font-medium" style="background:rgba(0,0,0,0.6);color:var(--text-secondary);backdrop-filter:blur(4px)">{{ listing.type }}</span>
          <div v-if="battery !== null" class="flex items-center gap-1 px-2 py-0.5 rounded-lg" style="background:rgba(0,0,0,0.65);backdrop-filter:blur(6px)">
            <svg width="20" height="11" viewBox="0 0 22 12" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="0.5" y="0.5" width="18" height="11" rx="2.5" :stroke="batteryColor" stroke-opacity="0.7"/>
              <path d="M19.5 4v4a1.5 1.5 0 000-4z" :fill="batteryColor" opacity="0.6"/>
              <rect x="2" y="2" :width="batteryFillWidth" height="8" rx="1.5" :fill="batteryColor"/>
            </svg>
            <span class="text-xs font-black" :style="'color:' + batteryColor">{{ battery }}%</span>
          </div>
        </div>
      </div>

      <!-- Content -->
      <div class="p-4">
        <h3 class="font-bold text-sm leading-snug mb-1.5 line-clamp-1 transition-colors group-hover:text-[#6fffd2]" style="color:var(--text-primary)">{{ listing.title }}</h3>

        <p class="text-xs mb-3 flex items-center gap-1.5" style="color:var(--text-muted)">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          {{ listing.city || '—' }}<span v-if="listing.country">, {{ listing.country }}</span>
        </p>

        <!-- Specs chips -->
        <div class="flex flex-wrap gap-1.5 mb-3 min-h-[22px]">
          <template v-if="listing.type === 'vehicle' && vehicle">
            <span v-if="vehicle.year" class="chip">{{ vehicle.year }}</span>
            <span v-if="vehicle.mileage" class="chip">{{ Number(vehicle.mileage).toLocaleString() }} km</span>
            <span v-if="vehicle.fuel_type" class="chip capitalize">{{ vehicle.fuel_type }}</span>
            <span v-if="vehicle.transmission" class="chip capitalize">{{ vehicle.transmission }}</span>
          </template>
          <template v-else-if="listing.type === 'property' && property">
            <span v-if="property.bedrooms" class="chip">{{ property.bedrooms }} bed</span>
            <span v-if="property.bathrooms" class="chip">{{ property.bathrooms }} bath</span>
            <span v-if="property.area_sqm" class="chip">{{ property.area_sqm }} m²</span>
          </template>
        </div>

        <!-- AI scores footer -->
        <div class="flex items-center gap-2 pt-3" style="border-top:1px solid var(--surface-overlay)">

          <!-- Trust score — linear bar, NOT circle -->
          <div v-if="trustScore !== null" class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs" style="color:var(--text-muted)">Trust</span>
              <span class="text-xs font-bold" :style="'color:' + trustColor">{{ trustScore }}</span>
            </div>
            <div style="height:3px;border-radius:2px;background:var(--border-subtle)">
              <div style="height:3px;border-radius:2px;transition:width .6s" :style="'width:' + trustScore + '%;background:' + trustColor"></div>
            </div>
          </div>

          <!-- Investment score badge -->
          <div v-if="listing.ai_investment_score" class="shrink-0 flex flex-col items-center px-2">
            <span class="text-xs font-black" style="color:var(--accent-violet)">{{ Math.round(listing.ai_investment_score) }}</span>
            <span class="text-xs" style="color:var(--text-muted)">ROI</span>
          </div>

          <!-- View count -->
          <div class="shrink-0 flex items-center gap-1 ml-auto" style="color:var(--text-muted)">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <span class="text-xs">{{ listing.view_count || 0 }}</span>
          </div>
        </div>

        <!-- AI Price label -->
        <div v-if="listing.ai_price_status" class="mt-2 text-xs font-semibold" :style="'color:' + batteryColor">
          {{ aiStatusLabel }}
        </div>
      </div>
    </div>
  </RouterLink>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { Listing, Vehicle, Property } from '@/types'

const props = defineProps<{ listing: Listing }>()
const imgError = ref(false)

const vehicle = computed((): Vehicle | null =>
  props.listing.type === 'vehicle' ? props.listing.listable as Vehicle : null
)
const property = computed((): Property | null =>
  props.listing.type === 'property' ? props.listing.listable as Property : null
)

const primaryImage = computed((): string | null => {
  if (imgError.value) return null
  const imgs = props.listing.images
  if (imgs && imgs.length > 0) return imgs[0]
  return props.listing.thumbnail ?? null
})

// Battery (price score 0-100)
const battery = computed((): number | null => {
  const v = props.listing.price_battery_percent ?? props.listing.ai_price_score
  return v != null ? Math.round(Number(v)) : null
})

const batteryColor = computed((): string => {
  const b = battery.value ?? 50
  if (b >= 80) return '#6fffd2'
  if (b >= 60) return '#fbbf24'
  return '#ef4444'
})

// Fill width: battery body inner width = 15px max (rect x=2, body width=18, so 18-2-2=14 usable)
const batteryFillWidth = computed((): number => {
  const b = battery.value ?? 50
  return Math.round((b / 100) * 14)
})

const batteryBg = computed((): string => {
  const b = battery.value ?? 50
  if (b >= 80) return 'background:rgba(111,255,210,0.1);border:1px solid rgba(111,255,210,0.2)'
  if (b >= 60) return 'background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.2)'
  return 'background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2)'
})

// Trust score — integer
const trustScore = computed((): number | null => {
  const v = props.listing.ai_trust_score
  return v != null ? Math.round(Number(v)) : null
})

const trustColor = computed((): string => {
  const s = trustScore.value ?? 0
  if (s >= 85) return '#6fffd2'
  if (s >= 70) return '#56d8ff'
  if (s >= 55) return '#fbbf24'
  return '#ef4444'
})

const aiStatusLabel = computed((): string => {
  const b = battery.value ?? 50
  if (b >= 90) return '✦ Excellent Price'
  if (b >= 75) return '✦ Good Price'
  if (b >= 55) return '~ Fair Price'
  if (b >= 35) return '▲ Slightly High'
  return '▲ Overpriced'
})

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
.chip {
  background: var(--surface-overlay);
  color: #8fa6bc;
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 2px 8px;
  font-size: 11px;
  white-space: nowrap;
}
</style>
