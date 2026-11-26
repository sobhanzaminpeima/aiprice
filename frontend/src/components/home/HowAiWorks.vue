<template>
  <section class="px-4 pb-16 max-w-7xl mx-auto" aria-labelledby="how-heading">
    <div class="how rounded-3xl p-6 md:p-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
      <div class="lg:col-span-4">
        <span class="eyebrow">✦ {{ $t('How AI price intelligence works') }}</span>
        <h2 id="how-heading" class="text-2xl md:text-3xl font-black mt-3 mb-3" style="color:var(--text-primary)">{{ $t('Turn listings into market intelligence') }}</h2>
        <p class="text-sm mb-5" style="color:var(--text-muted)">{{ $t('Our AI analyses comparable listings, price history and real market activity across North Cyprus to give you a clear, data-backed decision.') }}</p>
        <ul class="space-y-2.5">
          <li v-for="c in checks" :key="c" class="flex items-start gap-2 text-sm" style="color:var(--text-secondary)"><span class="tick">✓</span>{{ $t(c) }}</li>
        </ul>
      </div>

      <div class="lg:col-span-8">
        <div class="card rounded-2xl p-4 md:p-5 relative">
          <span v-if="!real" class="example">{{ $t('Example') }}</span>
          <component :is="real ? RouterLink : 'div'" v-bind="real ? { to: `/listings/${data.slug}` } : {}" class="flex items-center gap-4 mb-4" style="text-decoration:none">
            <img v-if="data.image" :src="data.image" alt="" class="w-28 h-20 rounded-xl object-cover shrink-0" />
            <div class="min-w-0 flex-1">
              <p class="text-lg font-black truncate" style="color:var(--text-primary)">{{ data.title }}</p>
              <p class="text-xs" style="color:var(--text-muted)">◉ {{ data.city }}, North Cyprus</p>
            </div>
            <div class="price-box shrink-0 text-right">
              <p class="text-[11px]" style="color:var(--text-muted)">{{ $t('Listed price') }}</p>
              <p class="text-2xl font-black" style="color:var(--text-primary)">{{ money(data.price) }}</p>
            </div>
          </component>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div class="tile">
              <p class="tl">{{ $t('AI fair price range') }}</p>
              <p class="text-xl font-black" style="color:#0567b5">{{ money(data.min) }} – {{ money(data.max) }}</p>
              <p class="text-[11px]" style="color:var(--text-muted)">{{ data.count ? $t('Market value based on {n} similar listings', { n: data.count }) : $t('AI rough estimate') }}</p>
            </div>
            <div class="tile">
              <p class="tl">{{ $t('AI deal signal') }}</p>
              <span class="inline-flex mt-1 px-4 py-1.5 rounded-full font-black text-sm" :style="signal.style">{{ $t(signal.label) }}</span>
              <div class="mt-2"><BatteryMeter :percent="data.battery" variant="bar" class="battery-light" /></div>
            </div>
            <div class="tile flex items-center justify-between">
              <div><p class="tl">{{ $t('Confidence') }}</p><p class="text-2xl font-black" style="color:#0567b5">{{ data.confidence }}%</p></div>
              <ConfidenceRing :value="data.confidence" :size="56" color="#16C784" style="color:var(--text-primary)" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div class="tile md:col-span-3" style="color:var(--text-muted)">
              <p class="font-bold text-sm mb-1" style="color:var(--text-primary)">{{ $t('Market price trend') }} <span class="font-normal text-xs" style="color:var(--text-muted)">({{ data.trendLabel }})</span></p>
              <PriceTrendChart :points="data.points" :current="data.price" :currency="data.currency" />
            </div>
            <div class="analysis md:col-span-2">
              <p class="font-bold text-sm mb-2" style="color:#0567b5">✦ {{ $t('AI analysis') }}</p>
              <p class="text-xs leading-relaxed mb-3" style="color:var(--text-secondary)">{{ data.text }}</p>
              <ul class="space-y-1.5 text-xs" style="color:var(--text-secondary)">
                <li>▤ {{ data.count ? $t('{n} similar listings analysed', { n: data.count }) : $t('AI estimate from market knowledge') }}</li>
                <li>▦ {{ $t('Last {n} months of market activity', { n: Math.max(data.points.length, 1) }) }}</li>
                <li>◉ {{ $t('North Cyprus market data') }}</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { t } from '@/i18n'
import type { Listing } from '@/types'
import PriceTrendChart from '@/components/listings/PriceTrendChart.vue'
import ConfidenceRing from '@/components/listings/ConfidenceRing.vue'
import BatteryMeter from '@/components/listings/BatteryMeter.vue'

const props = defineProps<{ example: Listing | null }>()
const checks = ['Analyses real, up-to-date listings', 'Calculates a fair price range', 'Shows the market trend and price direction', 'Gives a confidence score and a deal signal']
type Pt = { month: string; median: number; count: number }
const trend = ref<Pt[]>([])

watch(() => props.example?.slug, async (slug) => {
  trend.value = []
  if (!slug) return
  try { trend.value = (await client.get(`/listings/${slug}/market-trend`)).data?.points ?? [] } catch { /* optional */ }
}, { immediate: true })

// A real analysed listing is shown once one has a price check and a trend;
// until then an illustrative example (clearly badged) explains the feature.
const real = computed(() => !!props.example && trend.value.length >= 2)
const months = (n: number) => Array.from({ length: n }, (_, i) => { const d = new Date(); d.setMonth(d.getMonth() - (n - 1 - i)); return d.toISOString().slice(0, 7) })
const sample = computed(() => {
  const m = months(6)
  const medians = [2150, 2250, 2100, 2050, 2000, 2000]
  return {
    slug: '', title: '2006 Ford Fiesta', city: 'Kyrenia', image: null as string | null, currency: 'GBP', price: 2000, min: 1700, max: 2300, count: 36, confidence: 75, battery: 92,
    status: 'fair', points: m.map((month, i) => ({ month, median: medians[i] ?? 2000, count: 6 })), trendLabel: 'Ford Fiesta, North Cyprus',
    text: t('This listing is within the fair price range. Similar vehicles are listed between £1,700 and £2,300, with stable prices in recent months.'),
  }
})
const data = computed(() => {
  const l = props.example
  if (!real.value || !l) return sample.value
  return {
    slug: l.slug, title: l.title, city: l.city || 'North Cyprus', image: l.thumbnail || l.images?.[0] || null, currency: l.currency || 'GBP',
    price: Number(l.price), min: Number(l.market_min_price), max: Number(l.market_max_price), count: Number(l.ai_comparable_count ?? 0),
    confidence: Math.round(Number(l.ai_confidence_score ?? 0)), battery: l.price_battery_percent != null ? Number(l.price_battery_percent) : null,
    status: l.ai_price_status ?? 'fair', points: trend.value, trendLabel: l.title,
    text: l.ai_analysis || ((l as any).ai_score_breakdown?.price ?? []).slice(0, 2).join(' '),
  }
})
const signal = computed(() => data.value.status === 'fair'
  ? { label: '✓ Fair price', style: 'background:#e3f8ee;color:#046c4e;border:1px solid #9fe3c4' }
  : data.value.status === 'slightly_overpriced'
    ? { label: '▲ Slightly high', style: 'background:#fff3dd;color:#8a4b00;border:1px solid #f5cf8d' }
    : { label: '▲ High for this market', style: 'background:#ffe9e9;color:#a11d1d;border:1px solid #f3b0b0' })
const money = (v: number) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: data.value.currency || 'GBP', maximumFractionDigits: 0 }).format(v)
</script>

<style scoped>
.how { background: linear-gradient(160deg, #eef5fc, #f7fbff); border: 1px solid var(--border-subtle); }
:root[data-theme="dark"] .how { background: var(--bg-surface); }
.eyebrow { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--accent-cyan);background:rgba(var(--accent-cyan-rgb),.1) }
.tick { color:var(--accent-teal);font-weight:900 }
.card { background: var(--bg-card); border: 1px solid var(--border-subtle); box-shadow: 0 18px 44px rgba(8,24,39,.1); }
.tile { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 12px 14px; }
.tl { font-size: 11px; font-weight: 700; color: var(--text-muted); }
.price-box { border: 1px solid var(--border-subtle); border-radius: 14px; padding: 8px 14px; }
.analysis { background: rgba(var(--accent-cyan-rgb), .06); border: 1px solid rgba(var(--accent-cyan-rgb), .2); border-radius: 14px; padding: 14px; }
.example { position:absolute;top:-10px;right:16px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;color:#8a4b00;background:#fff3dd;border:1px solid #f5cf8d }
.battery-light :deep(.label) { color: var(--text-muted) !important; }
</style>
