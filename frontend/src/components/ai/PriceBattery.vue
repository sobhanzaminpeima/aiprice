<template>
  <div class="bg-[var(--bg-surface)] rounded-xl p-4 border border-[var(--border-subtle)]">
    <div class="flex items-center justify-between mb-3">
      <span class="ai-badge">AI Price Battery™</span>
      <span class="text-xs text-[var(--text-muted)]">{{ confidence }}% confidence</span>
    </div>

    <!-- Battery visual -->
    <div class="flex items-center gap-3 mb-4">
      <div class="relative flex-1 h-8 bg-[var(--surface-overlay)] rounded-full overflow-hidden">
        <div
          class="absolute inset-y-0 left-0 rounded-full transition-all duration-700"
          :class="batteryClass"
          :style="{ width: `${percent}%` }"
        />
        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-[var(--text-primary)]">
          {{ percent }}%
        </span>
      </div>
      <div class="w-3 h-5 rounded-r border-2 border-l-0" :class="batteryBorderClass" />
    </div>

    <!-- Status badge -->
    <div class="flex items-center justify-between mb-4">
      <span
        class="px-3 py-1 rounded-full text-sm font-semibold"
        :class="statusClass"
      >
        {{ statusLabel }}
      </span>
      <span class="text-sm" :class="priceDiffClass">
        {{ priceDiffText }}
      </span>
    </div>

    <!-- Market range -->
    <div v-if="minPrice && maxPrice" class="border-t border-[var(--border-subtle)] pt-3">
      <p class="text-xs text-[var(--text-muted)] mb-2">Market Range</p>
      <div class="flex items-center justify-between">
        <span class="text-sm text-[var(--text-secondary)]">{{ formatPrice(minPrice) }}</span>
        <div class="flex-1 mx-3 h-1 bg-[var(--border-strong)] rounded relative">
          <div
            class="absolute top-0 h-full rounded"
            :class="batteryClass"
            :style="{ left: '0%', width: `${markerPosition}%` }"
          />
          <div
            class="absolute -top-1.5 w-4 h-4 rounded-full border-2 border-[var(--border-subtle)] shadow"
            :class="markerColorClass"
            :style="{ left: `calc(${markerPosition}% - 8px)` }"
          />
        </div>
        <span class="text-sm text-[var(--text-secondary)]">{{ formatPrice(maxPrice) }}</span>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-2 text-center">
        Market avg: <span class="text-[var(--text-primary)] font-medium">{{ formatPrice(avgPrice) }}</span>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  percent: number
  status: 'fair' | 'slightly_overpriced' | 'overpriced' | null
  listingPrice: number
  minPrice?: number | null
  maxPrice?: number | null
  avgPrice?: number | null
  confidence?: number
  currency?: string
}>()

const batteryClass = computed(() => {
  const p = props.percent
  if (p >= 90) return 'bg-emerald-500'
  if (p >= 70) return 'bg-amber-500'
  return 'bg-red-500'
})

const batteryBorderClass = computed(() => {
  const p = props.percent
  if (p >= 90) return 'border-emerald-500'
  if (p >= 70) return 'border-amber-500'
  return 'border-red-500'
})

const statusClass = computed(() => {
  if (props.status === 'fair') return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
  if (props.status === 'slightly_overpriced') return 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
  return 'bg-red-500/20 text-red-400 border border-red-500/30'
})

const statusLabel = computed(() => {
  if (props.status === 'fair') return '✓ Fair Price'
  if (props.status === 'slightly_overpriced') return '~ Slightly Overpriced'
  return '⚠ Overpriced'
})

const markerColorClass = computed(() => {
  if (props.percent >= 90) return 'bg-emerald-400'
  if (props.percent >= 70) return 'bg-amber-400'
  return 'bg-red-400'
})

const markerPosition = computed(() => {
  if (!props.minPrice || !props.maxPrice) return 50
  const range = props.maxPrice - props.minPrice
  if (range === 0) return 50
  return Math.min(100, Math.max(0, ((props.listingPrice - props.minPrice) / range) * 100))
})

const priceDiff = computed(() => {
  if (!props.avgPrice) return null
  return ((props.listingPrice - props.avgPrice) / props.avgPrice) * 100
})

const priceDiffText = computed(() => {
  if (!priceDiff.value) return ''
  const diff = priceDiff.value
  if (Math.abs(diff) < 1) return '≈ At market value'
  return diff > 0
    ? `+${diff.toFixed(1)}% above market`
    : `${diff.toFixed(1)}% below market`
})

const priceDiffClass = computed(() => {
  if (!priceDiff.value) return 'text-[var(--text-muted)]'
  return priceDiff.value > 5 ? 'text-red-400' : priceDiff.value < -5 ? 'text-emerald-400' : 'text-amber-400'
})

function formatPrice(val?: number | null): string {
  if (!val) return '-'
  const currency = props.currency || 'GBP'
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency, maximumFractionDigits: 0 }).format(val)
}
</script>
