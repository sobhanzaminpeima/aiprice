<template>
  <span class="battery" :class="`battery--${variant}`" :title="value === null ? $t('AI price check pending') : $t('Price battery: how good the price is compared with similar listings (100% = best value)')" role="img" :aria-label="value === null ? $t('AI price check pending') : `${$t('Price battery')} ${value}%`">
    <span class="shell" :style="{ borderColor: color }">
      <span v-for="i in 5" :key="i" class="cell" :style="{ background: value !== null && i <= cells ? color : 'transparent', opacity: value !== null && i <= cells ? 1 : 0.25, outline: value === null ? `1px solid ${color}` : 'none' }"></span>
    </span>
    <span class="cap" :style="{ background: color }"></span>
    <strong class="pct" :style="{ color: variant === 'chip' ? '#fff' : color }">{{ value === null ? 'AI…' : value + '%' }}</strong>
    <span v-if="variant === 'bar'" class="label">{{ $t(value === null ? 'AI price check pending' : label) }}</span>
  </span>
</template>
<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ percent: number | string | null | undefined; variant?: 'chip' | 'bar' }>(), { variant: 'chip' })
const value = computed(() => (props.percent == null || props.percent === '' ? null : Math.max(0, Math.min(100, Math.round(Number(props.percent))))))
const cells = computed(() => Math.max(1, Math.ceil(((value.value ?? 0) / 100) * 5)))
// Fixed colours: the chip sits on photos and the bar on the dark price card in both themes.
const color = computed(() => { if (value.value === null) return '#91a1b5'; const v = value.value; return v >= 85 ? '#16C784' : v >= 70 ? '#12C5E8' : v >= 50 ? '#fbbf24' : '#f87171' })
const label = computed(() => { const v = value.value ?? 0; return v >= 85 ? 'Great value' : v >= 70 ? 'Good value' : v >= 50 ? 'Fair value' : 'Expensive' })
</script>
<style scoped>
.battery { display: inline-flex; align-items: center; gap: 4px; }
.battery--chip { padding: 4px 8px; border-radius: 10px; background: rgba(8,24,39,.72); backdrop-filter: blur(6px); }
.battery--bar { gap: 6px; }
.shell { display: inline-flex; gap: 2px; padding: 2px; border: 1.5px solid; border-radius: 4px; }
.battery--chip .cell { width: 4px; height: 9px; }
.battery--bar .cell { width: 9px; height: 16px; }
.cell { border-radius: 1.5px; }
.cap { width: 2px; border-radius: 0 2px 2px 0; }
.battery--chip .cap { height: 6px; margin-left: -3px; }
.battery--bar .cap { height: 10px; margin-left: -5px; }
.battery--chip .pct { font-size: 11px; }
.battery--bar .pct { font-size: 18px; font-weight: 900; }
.label { font-size: 12px; font-weight: 700; color: #cfe0f3; }
</style>
