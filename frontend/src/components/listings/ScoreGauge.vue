<template>
  <div class="text-center p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)" role="meter" :aria-label="label" :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100">
    <div class="text-2xl font-black mb-1" :style="{ color }">{{ value }}</div>
    <div class="text-xs" style="color:var(--text-muted)">{{ label }}</div>
    <div class="mt-2 h-1 rounded-full" style="background:var(--border-subtle)">
      <div class="h-1 rounded-full" :style="{ width: pct + '%', background: color }"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ label: string; value: number | string; color: string }>()
const pct = computed(() => Math.max(0, Math.min(100, Number(props.value) || 0)))
</script>
