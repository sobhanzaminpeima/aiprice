<template>
  <div class="rounded-2xl p-5 h-full" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
    <h3 class="font-bold text-sm mb-3 flex items-center gap-2" style="color:var(--text-primary)">⚙ {{ $t('Why this score?') }}</h3>
    <ul class="space-y-2">
      <li v-for="c in checks" :key="c.label" class="flex items-start gap-2 text-xs" style="color:var(--text-secondary)">
        <span class="tick">✓</span><span><strong style="color:var(--text-primary)">{{ $t(c.label) }}:</strong> {{ c.value }}</span>
      </li>
    </ul>
    <button v-if="extra.length" class="text-xs font-semibold mt-3" style="color:var(--accent-cyan)" @click="open = !open">{{ open ? $t('Hide details') : $t('All reasons') }}</button>
    <ul v-if="open" class="mt-2 space-y-1">
      <li v-for="(r, i) in extra" :key="i" class="text-[11px]" style="color:var(--text-muted)">• {{ r }}</li>
    </ul>
  </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@/i18n'
import type { Listing, Property, Vehicle } from '@/types'

const props = defineProps<{ listing: Listing }>()
const open = ref(false)

const demand = computed(() => {
  const d = Number((props.listing as { ai_demand_score?: number | null }).ai_demand_score ?? NaN)
  if (Number.isNaN(d)) return t('Not yet measured')
  return t(d >= 70 ? 'High' : d >= 45 ? 'Moderate' : 'Low')
})
// Each line states a fact the score used, from the listing itself.
const checks = computed(() => {
  const l = props.listing
  const rows: { label: string; value: string }[] = []
  if (l.type === 'vehicle') {
    const v = l.listable as Vehicle | null
    rows.push({ label: 'Year', value: v?.year ? String(v.year) : t('Not specified') })
    rows.push({ label: 'Mileage', value: v?.mileage ? `${Number(v.mileage).toLocaleString()} km` : t('Not specified') })
    rows.push({ label: 'Condition', value: v?.condition ? t(v.condition.charAt(0).toUpperCase() + v.condition.slice(1)) : t('Not specified') })
  } else {
    const p = l.listable as Property | null
    rows.push({ label: 'Size', value: p?.area_sqm ? `${p.area_sqm} m²` : t('Not specified') })
    rows.push({ label: 'Bedrooms', value: p?.bedrooms != null ? String(p.bedrooms) : t('Not specified') })
    rows.push({ label: 'Location', value: l.city || t('Not specified') })
  }
  rows.push({ label: 'Market demand', value: demand.value })
  rows.push({ label: 'Comparable listings', value: l.ai_comparable_count ? t('{n} analysed in North Cyprus', { n: l.ai_comparable_count }) : t('Not enough yet') })
  return rows
})
const extra = computed(() => {
  const b = (props.listing as { ai_score_breakdown?: Record<string, unknown> }).ai_score_breakdown ?? {}
  return ['price', 'trust', 'demand', 'investment'].flatMap((k) => (Array.isArray(b[k]) ? (b[k] as string[]) : []))
})
</script>
<style scoped>
.tick { color: var(--accent-teal); font-weight: 900; }
</style>
