<template>
  <div>
    <svg v-if="points.length >= 2" :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" :aria-label="$t('Market price trend')">
      <defs>
        <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#16C784" stop-opacity=".35" /><stop offset="1" stop-color="#16C784" stop-opacity="0" />
        </linearGradient>
      </defs>
      <g v-for="t in yTicks" :key="t.v">
        <line :x1="PL" :x2="W - PR" :y1="t.y" :y2="t.y" stroke="currentColor" stroke-opacity=".12" />
        <text :x="PL - 6" :y="t.y + 3" text-anchor="end" font-size="10" fill="currentColor" fill-opacity=".6">{{ short(t.v) }}</text>
      </g>
      <path :d="area" :fill="`url(#${gid})`" />
      <path :d="line" fill="none" stroke="#16C784" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
      <g v-for="(p, i) in plotted" :key="p.month">
        <circle :cx="p.x" :cy="p.y" r="3" fill="#16C784" />
        <text :x="p.x" :y="H - 6" text-anchor="middle" font-size="10" fill="currentColor" fill-opacity=".6">{{ monthLabel(p.month) }}</text>
        <title>{{ monthLabel(p.month) }}: {{ money(p.median) }} ({{ p.count }} {{ $t('listings') }})</title>
        <circle v-if="i === plotted.length - 1" :cx="p.x" :cy="p.y" r="6" fill="none" stroke="#16C784" stroke-opacity=".5" />
      </g>
      <g v-if="current != null && currentY !== null">
        <line :x1="PL" :x2="W - PR" :y1="currentY" :y2="currentY" stroke="#12C5E8" stroke-dasharray="4 4" stroke-opacity=".8" />
        <rect :x="W - PR - 118" :y="Math.max(2, currentY - 26)" width="118" height="20" rx="6" fill="#0b1e31" fill-opacity=".92" />
        <text :x="W - PR - 59" :y="Math.max(2, currentY - 26) + 14" text-anchor="middle" font-size="10.5" font-weight="700" fill="#fff">{{ money(current ?? 0) }} · {{ $t('this listing') }}</text>
      </g>
    </svg>
    <p v-else class="text-xs py-6 text-center" style="color:var(--text-muted)">
      {{ $t('The price trend appears once at least two months have enough similar listings.') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { locale } from '@/i18n'

const props = defineProps<{ points: { month: string; median: number; count: number }[]; current?: number | null; currency?: string }>()
const W = 560, H = 190, PL = 46, PR = 12, PT = 14, PB = 24
const gid = `g${Math.random().toString(36).slice(2, 8)}`

const lo = computed(() => Math.min(...props.points.map((p) => p.median), props.current ?? Infinity))
const hi = computed(() => Math.max(...props.points.map((p) => p.median), props.current ?? -Infinity))
const pad = computed(() => Math.max((hi.value - lo.value) * 0.2, hi.value * 0.03, 1))
const min = computed(() => lo.value - pad.value)
const max = computed(() => hi.value + pad.value)
const y = (v: number) => PT + (1 - (v - min.value) / (max.value - min.value || 1)) * (H - PT - PB)
const plotted = computed(() => props.points.map((p, i) => ({ ...p, x: PL + (props.points.length === 1 ? 0 : (i / (props.points.length - 1)) * (W - PL - PR)), y: y(p.median) })))
const line = computed(() => plotted.value.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '))
const area = computed(() => {
  const first = plotted.value[0]; const last = plotted.value[plotted.value.length - 1]
  return first && last ? `${line.value} L${last.x},${H - PB} L${first.x},${H - PB} Z` : ''
})
const yTicks = computed(() => [0, 1, 2, 3].map((i) => { const v = min.value + ((max.value - min.value) * i) / 3; return { v, y: y(v) } }))
const currentY = computed(() => (props.current != null ? y(props.current) : null))

const loc = () => (locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB')
const money = (v: number) => new Intl.NumberFormat(loc(), { style: 'currency', currency: props.currency || 'GBP', maximumFractionDigits: 0 }).format(v)
const short = (v: number) => new Intl.NumberFormat(loc(), { notation: 'compact', maximumFractionDigits: 1 }).format(v)
const monthLabel = (m: string) => new Intl.DateTimeFormat(loc(), { month: 'short' }).format(new Date(`${m}-01T12:00:00Z`))
</script>
