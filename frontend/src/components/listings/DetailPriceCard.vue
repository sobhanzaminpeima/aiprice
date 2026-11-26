<template>
  <div class="price-card rounded-3xl p-6">
    <span class="tag">◎ {{ $t(asking ? "Seller's asking price" : 'Asking price') }}</span>
    <p class="text-4xl font-black mt-3 mb-1" style="color:#fff">{{ money(listing.price) }}</p>
    <p v-if="perSqm" class="text-sm mb-1" style="color:#a9bdd3">{{ perSqm }}</p>
    <p v-if="listing.price_negotiable" class="text-sm mb-1" style="color:#4fe0b0">✓ {{ $t('Price is negotiable') }}</p>

    <template v-if="hasRange">
      <div class="box mt-4">
        <p class="label">{{ $t('AI fair price range') }}</p>
        <p class="text-2xl font-black" style="color:#4fe0b0">{{ money(listing.market_min_price!) }} – {{ money(listing.market_max_price!) }}</p>
      </div>
      <div v-if="listing.price_battery_percent != null" class="box mt-3">
        <p class="label mb-1.5">{{ $t('Price battery') }}</p>
        <BatteryMeter :percent="listing.price_battery_percent" variant="bar" />
      </div>
      <div class="grid grid-cols-2 gap-3 mt-3">
        <div class="box">
          <p class="label">{{ $t('Deal signal') }}</p>
          <span class="inline-block mt-1 px-2.5 py-1 rounded-full text-xs font-black" :style="signal.style">{{ $t(signal.label) }}</span>
        </div>
        <div class="box flex items-center justify-between gap-2">
          <div><p class="label">{{ $t('Confidence') }}</p><p class="text-xl font-black" style="color:#fff">{{ Math.round(Number(listing.ai_confidence_score ?? 0)) }}%</p></div>
          <ConfidenceRing :value="Number(listing.ai_confidence_score ?? 0)" :size="44" :color="signal.ring" style="color:transparent" />
        </div>
      </div>
    </template>
    <p v-else class="box mt-4 text-xs" style="color:#cfe0f3">{{ $t('Waiting for similar listings') }} — {{ $t('the price check switches on as more listings are added.') }}</p>

    <button type="button" class="send mt-5" @click="$emit('contact')">➤ {{ $t('Send to seller') }}</button>
    <button type="button" class="save mt-3" @click="$emit('favorite')" :aria-pressed="favorited">
      <span aria-hidden="true">{{ favorited ? '♥' : '♡' }}</span> {{ favorited ? $t('Saved') : $t('Save listing') }}
    </button>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import ConfidenceRing from '@/components/listings/ConfidenceRing.vue'
import BatteryMeter from '@/components/listings/BatteryMeter.vue'
import type { Listing } from '@/types'

const props = defineProps<{ listing: Listing; favorited: boolean; asking?: boolean; areaSqm?: number | null }>()
defineEmits<{ contact: []; favorite: [] }>()
const money = (v: number) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: props.listing.currency || 'GBP', maximumFractionDigits: 0 }).format(v)
const hasRange = computed(() => !!props.listing.market_min_price && !!props.listing.market_max_price && props.listing.ai_price_status !== 'insufficient_data')
const perSqm = computed(() => (props.areaSqm ? `${money(Number(props.listing.price) / props.areaSqm)}/m²` : ''))
const signal = computed(() => {
  const s = props.listing.ai_price_status
  if (s === 'fair') return { label: '✓ Fair price', ring: '#16C784', style: 'background:rgba(22,199,132,.18);color:#4fe0b0;border:1px solid rgba(22,199,132,.4)' }
  if (s === 'slightly_overpriced') return { label: '▲ Slightly high', ring: '#fbbf24', style: 'background:rgba(251,191,36,.16);color:#fbbf24;border:1px solid rgba(251,191,36,.4)' }
  return { label: '▲ High for this market', ring: '#f87171', style: 'background:rgba(248,113,113,.16);color:#f87171;border:1px solid rgba(248,113,113,.4)' }
})
</script>
<style scoped>
.price-card { background: linear-gradient(160deg, #0a2540 0%, #08304f 55%, #0b4a6a 100%); border: 1px solid rgba(120,190,255,.3); box-shadow: 0 24px 60px rgba(2,10,22,.35); color: #fff; }
.tag { display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;color:#cfe0f3;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14) }
.box { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.13); border-radius: 14px; padding: 12px 14px; }
.label { font-size: 11px; font-weight: 600; color: #a9bdd3; }
.send { width:100%;padding:13px;border-radius:14px;font-size:14px;font-weight:800;color:#04111d;background:linear-gradient(135deg,#12C5E8,#4fe0b0);box-shadow:0 10px 24px rgba(18,197,232,.35) }
.save { width:100%;padding:12px;border-radius:14px;font-size:14px;font-weight:700;color:#fff;background:transparent;border:1px solid rgba(255,255,255,.35) }
.save:hover { background:rgba(255,255,255,.06) }
</style>
