<template>
  <section class="deal-analysis rounded-3xl overflow-hidden" :aria-label="$t('AI Deal Analysis')">
    <header class="flex items-center gap-3 px-6 pt-6">
      <span class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-sm" style="background:linear-gradient(135deg,#12C5E8,#16C784);color:#04111d">AI</span>
      <div>
        <h2 class="font-black text-lg" style="color:#fff">{{ $t('AI Deal Analysis') }}</h2>
        <p class="text-xs" style="color:#a9bdd3">{{ $t('Real market data and AI analysis to help you decide if this is a good price.') }}</p>
      </div>
    </header>

    <template v-if="hasEstimate">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 p-6">
        <div class="stat">
          <p class="stat-label">{{ $t('AI fair price range') }}</p>
          <p class="text-lg font-black whitespace-nowrap" style="color:#4fe0b0">{{ money(listing.market_min_price!) }} – {{ money(listing.market_max_price!) }}</p>
        </div>
        <div class="stat">
          <p class="stat-label">{{ $t('Deal signal') }}</p>
          <span class="inline-block px-3 py-1 rounded-full text-sm font-black mt-1" :style="signal.style">{{ $t(signal.label) }}</span>
        </div>
        <div class="stat flex items-center gap-3">
          <ConfidenceRing :value="Number(listing.ai_confidence_score ?? 0)" :size="56" :color="signal.ring" style="color:#fff" />
          <div><p class="stat-label">{{ $t('Confidence') }}</p><p class="text-[11px]" style="color:#a9bdd3">{{ $t('How reliable the price check is') }}</p></div>
        </div>
        <div class="stat">
          <p class="stat-label">{{ $t('{n} similar listings analysed', { n: count }) }}</p>
          <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold mt-1" :style="quality.style">{{ $t('Data quality') }}: {{ $t(quality.label) }}</span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 px-6 pb-6">
        <div class="lg:col-span-3 rounded-2xl p-4" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#cfe0f3">
          <div class="flex items-baseline justify-between mb-1">
            <h3 class="font-bold text-sm" style="color:#fff">{{ $t('Market price trend') }}</h3>
            <span v-if="trend?.change_pct != null" class="text-xs font-bold" :style="{ color: trend.change_pct >= 0 ? '#fbbf24' : '#4fe0b0' }">{{ trend.change_pct > 0 ? '+' : '' }}{{ trend.change_pct }}% · {{ trend.points.length }} {{ $t('months') }}</span>
          </div>
          <p class="text-[11px] mb-2" style="color:#a9bdd3">{{ $t(basisText) }}</p>
          <PriceTrendChart :points="trend?.points ?? []" :current="Number(listing.price)" :currency="listing.currency" />
        </div>
        <div class="lg:col-span-2 rounded-2xl p-4" style="background:rgba(18,197,232,.08);border:1px solid rgba(18,197,232,.25)">
          <h3 class="font-bold text-sm mb-2 flex items-center gap-2" style="color:#fff">✦ {{ $t('AI explanation') }}</h3>
          <p class="text-sm leading-relaxed" style="color:#dce9f7">{{ explanation }}</p>
          <p class="text-[11px] mt-3" style="color:#a9bdd3">{{ $t('An estimate from asking prices, not a valuation or a guarantee.') }}</p>
        </div>
      </div>
    </template>

    <div v-else class="p-6 pt-4">
      <div class="rounded-2xl p-5 text-sm" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#dce9f7">
        {{ $t('We compare each price with similar listings. There are not enough yet — the price check switches on as more listings are added.') }}
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import client from '@/api/client'
import { locale } from '@/i18n'
import type { Listing } from '@/types'
import PriceTrendChart from '@/components/listings/PriceTrendChart.vue'
import ConfidenceRing from '@/components/listings/ConfidenceRing.vue'

const props = defineProps<{ listing: Listing }>()
const trend = ref<{ points: { month: string; median: number; count: number }[]; basis: string | null; change_pct: number | null } | null>(null)

const hasEstimate = computed(() => !!props.listing.market_min_price && !!props.listing.market_max_price && props.listing.ai_price_status !== 'insufficient_data')
const count = computed(() => Number(props.listing.ai_comparable_count ?? 0))
const money = (v: number) => new Intl.NumberFormat(locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB', { style: 'currency', currency: props.listing.currency || 'GBP', maximumFractionDigits: 0 }).format(v)

const signal = computed(() => {
  const s = props.listing.ai_price_status
  if (s === 'fair') return { label: '✓ Fair price', ring: '#16C784', style: 'background:rgba(22,199,132,.18);color:#4fe0b0;border:1px solid rgba(22,199,132,.4)' }
  if (s === 'slightly_overpriced') return { label: '▲ Slightly high', ring: '#fbbf24', style: 'background:rgba(251,191,36,.16);color:#fbbf24;border:1px solid rgba(251,191,36,.4)' }
  return { label: '▲ High for this market', ring: '#f87171', style: 'background:rgba(248,113,113,.16);color:#f87171;border:1px solid rgba(248,113,113,.4)' }
})
// How much the check can be trusted: many similar listings and a high confidence score.
const quality = computed(() => {
  const conf = Number(props.listing.ai_confidence_score ?? 0)
  if (count.value >= 10 && conf >= 70) return { label: 'Good', style: 'background:rgba(22,199,132,.18);color:#4fe0b0' }
  if (count.value >= 5 && conf >= 50) return { label: 'Fair', style: 'background:rgba(251,191,36,.16);color:#fbbf24' }
  return { label: 'Limited', style: 'background:rgba(248,113,113,.16);color:#f87171' }
})
const basisText = computed(() => ({ model: 'Same model, by month posted', brand: 'Same brand, by month posted', type_and_size: 'Same type and size, by month posted', type: 'Same property type, by month posted', category: 'All similar listings, by month posted' } as Record<string, string>)[trend.value?.basis ?? ''] ?? 'Median asking price by month posted')
// The AI's own sentence when there is one, otherwise the plain reasons the check produced.
const explanation = computed(() => props.listing.ai_analysis || ((props.listing as any).ai_score_breakdown?.price ?? []).slice(0, 3).join(' '))

async function load() {
  trend.value = null
  if (!props.listing.slug) return
  try { trend.value = (await client.get(`/listings/${props.listing.slug}/market-trend`)).data } catch { /* optional */ }
}
onMounted(load)
watch(() => props.listing.slug, load)
</script>

<style scoped>
.deal-analysis { background: linear-gradient(150deg, #0a2540 0%, #08304f 55%, #0a3b52 100%); border: 1px solid rgba(120,190,255,.22); box-shadow: 0 18px 48px rgba(2,10,22,.3); }
.stat { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; padding: 14px 16px; }
.stat-label { font-size: 11px; font-weight: 600; color: #a9bdd3; }
</style>
