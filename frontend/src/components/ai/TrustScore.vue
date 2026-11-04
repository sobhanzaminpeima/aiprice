<template>
  <div class="flex items-center gap-2">
    <div class="relative w-10 h-10">
      <svg viewBox="0 0 36 36" class="w-10 h-10 -rotate-90">
        <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" data-tone="track" stroke-width="3" />
        <circle
          cx="18" cy="18" r="15.9" fill="none"
          :style="{ stroke: strokeColor }"
          stroke-width="3"
          stroke-linecap="round"
          :stroke-dasharray="`${score} ${100 - score}`"
          class="transition-all duration-700"
        />
      </svg>
      <span class="absolute inset-0 flex items-center justify-center text-xs font-bold" :style="{ color: strokeColor }">
        {{ score }}
      </span>
    </div>
    <div>
      <p class="text-xs text-[var(--text-muted)]">AI Trust Score™</p>
      <p class="text-sm font-semibold" :style="{ color: strokeColor }">{{ label }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ score: number }>()

const strokeColor = computed(() => {
  if (props.score >= 80) return '#10b981'
  if (props.score >= 60) return '#0ea5e9'
  if (props.score >= 40) return '#f59e0b'
  return 'var(--danger)'
})

const label = computed(() => {
  if (props.score >= 80) return 'Highly Trusted'
  if (props.score >= 60) return 'Trusted'
  if (props.score >= 40) return 'Moderate'
  return 'Low Trust'
})
</script>
