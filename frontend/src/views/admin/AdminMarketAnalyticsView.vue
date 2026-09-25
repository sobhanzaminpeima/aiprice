<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Market <span class="gradient-text">Analytics</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Computed from active AIPrice listings and their AI scores. Segments without scored listings show “—”.</p>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-6 text-sm" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-4"><div v-for="i in 3" :key="i" class="h-44 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div></div>

    <template v-else-if="data">
      <!-- Market indexes -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div v-for="t in types" :key="t.key" class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-center justify-between mb-4">
            <p class="font-bold" style="color:var(--text-primary)">{{ t.label }}</p>
            <p class="text-3xl font-black" :style="{ color: t.color }">{{ index(t.key)?.index ?? '—' }}</p>
          </div>
          <p class="text-xs mb-4" style="color:var(--text-muted)">{{ index(t.key)?.listings ?? 0 }} active · {{ index(t.key)?.scored ?? 0 }} AI-scored</p>
          <div class="space-y-2">
            <div v-for="m in ['trust', 'demand', 'deal', 'investment']" :key="m">
              <div class="flex justify-between text-xs mb-1"><span class="capitalize" style="color:var(--text-secondary)">{{ m }}</span><span style="color:var(--text-primary)">{{ index(t.key)?.[m] ?? '—' }}</span></div>
              <div class="h-1.5 rounded-full" style="background:var(--border-subtle)"><div class="h-1.5 rounded-full" :style="`width:${index(t.key)?.[m] ?? 0}%;background:${t.color}`"></div></div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- City prices -->
        <div class="lg:col-span-2 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-center justify-between mb-5">
            <h2 class="font-bold" style="color:var(--text-primary)">Prices by city</h2>
            <div class="flex gap-1">
              <button v-for="t in types" :key="t.key" @click="cityType = t.key" class="px-3 py-1 rounded-lg text-xs" :style="cityType === t.key ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal)' : 'color:var(--text-muted)'">{{ t.short }}</button>
            </div>
          </div>
          <p v-if="!cityRows.length" class="text-sm py-6 text-center" style="color:var(--text-muted)">No active listings in this segment.</p>
          <table v-else class="w-full text-xs">
            <thead><tr style="color:var(--text-muted);border-bottom:1px solid var(--border-subtle)"><th class="text-left pb-2">City</th><th class="text-right pb-2">Listings</th><th class="text-right pb-2">Average</th><th class="text-right pb-2">Range</th><th class="text-right pb-2">90-day trend</th></tr></thead>
            <tbody>
              <tr v-for="c in cityRows" :key="c.city + c.currency" class="border-b" style="border-color:var(--surface-overlay)">
                <td class="py-2.5 font-semibold" style="color:var(--text-primary)">{{ c.city }}</td>
                <td class="py-2.5 text-right" style="color:var(--text-secondary)">{{ c.listings }}</td>
                <td class="py-2.5 text-right font-bold" style="color:var(--accent-teal)">{{ money(c.avg_price, c.currency) }}</td>
                <td class="py-2.5 text-right" style="color:var(--text-muted)">{{ money(c.min_price, c.currency, true) }} – {{ money(c.max_price, c.currency, true) }}</td>
                <td class="py-2.5 text-right" :style="c.trend_pct === null ? 'color:var(--text-muted)' : c.trend_pct >= 0 ? 'color:var(--accent-teal)' : 'color:#ef4444'">{{ c.trend_pct === null ? '—' : `${c.trend_pct > 0 ? '+' : ''}${c.trend_pct}%` }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Price status -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-5" style="color:var(--text-primary)">AI price verdicts</h2>
          <p v-if="!priceStatusTotal" class="text-sm" style="color:var(--text-muted)">No listings scored yet.</p>
          <div v-else class="space-y-3">
            <div v-for="(n, status) in data.price_status" :key="status">
              <div class="flex justify-between text-xs mb-1"><span class="capitalize" style="color:var(--text-secondary)">{{ String(status).replace(/_/g, ' ') }}</span><span class="font-bold" style="color:var(--text-primary)">{{ n }}</span></div>
              <div class="h-1.5 rounded-full" style="background:var(--border-subtle)"><div class="h-1.5 rounded-full" :style="`width:${(Number(n) / priceStatusTotal) * 100}%;background:${verdictColor(String(status))}`"></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Best deals -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Top deals right now <span class="text-xs font-normal" style="color:var(--text-muted)">by AI deal score</span></h2>
        <p v-if="!data.opportunities.length" class="text-sm" style="color:var(--text-muted)">No deal scores computed yet.</p>
        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <RouterLink v-for="o in data.opportunities" :key="o.id" :to="`/listings/${o.slug}`" target="_blank" class="p-4 rounded-xl block hover:opacity-90" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
            <div class="flex justify-between mb-2">
              <span class="text-xs capitalize" style="color:var(--text-muted)">{{ o.type }} · {{ o.city }}</span>
              <span class="text-xs font-black" style="color:var(--accent-teal)">{{ Math.round(o.ai_deal_score) }}</span>
            </div>
            <p class="text-sm font-semibold mb-1 truncate" style="color:var(--text-primary)">{{ o.title }}</p>
            <p class="text-xs" style="color:var(--text-secondary)">{{ money(o.price, o.currency) }} · <span class="capitalize">{{ (o.ai_price_status || '').replace(/_/g, ' ') }}</span></p>
          </RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const types = [
  { key: 'vehicle', label: 'Cars market', short: 'Cars', color: '#6fffd2' },
  { key: 'property', label: 'Property market', short: 'Property', color: '#56d8ff' },
  { key: 'rental', label: 'Rental market', short: 'Rentals', color: '#a78bfa' },
]

const loading = ref(true)
const error = ref('')
const data = ref<any>(null)
const cityType = ref('vehicle')

const index = (type: string) => data.value?.indexes?.find((i: any) => i.type === type)
const cityRows = computed(() => (data.value?.cities ?? []).filter((c: any) => c.type === cityType.value))
const priceStatusTotal = computed(() => Object.values(data.value?.price_status ?? {}).reduce((s: number, n: any) => s + Number(n), 0))

function verdictColor(s: string) {
  if (s.includes('under') || s === 'great_deal') return '#6fffd2'
  if (s === 'fair') return '#56d8ff'
  if (s.includes('slightly')) return '#fbbf24'
  return '#ef4444'
}

function money(v: number, currency = 'GBP', compact = false) {
  try {
    // min must be set with max: currencies default to min 2, and max < min throws a RangeError.
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', minimumFractionDigits: 0, maximumFractionDigits: 0, notation: compact ? 'compact' : 'standard' }).format(Number(v))
  } catch {
    return `${currency} ${Number(v).toLocaleString()}`
  }
}

onMounted(async () => {
  try {
    data.value = (await client.get('/admin/market/overview')).data
    const firstWithData = types.find((t) => data.value.cities.some((c: any) => c.type === t.key))
    if (firstWithData) cityType.value = firstWithData.key
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load market analytics.')
  } finally {
    loading.value = false
  }
})
</script>
