<template>
  <div class="relative shrink-0" :style="{ width: size + 'px', height: size + 'px' }" role="img" :aria-label="`${$t('Confidence')} ${Math.round(value)}%`">
    <svg :width="size" :height="size" viewBox="0 0 44 44">
      <circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-opacity=".15" stroke-width="5" />
      <circle cx="22" cy="22" r="18" fill="none" :stroke="color" stroke-width="5" stroke-linecap="round" :stroke-dasharray="`${(Math.min(100, Math.max(0, value)) / 100) * 113.1} 113.1`" transform="rotate(-90 22 22)" />
    </svg>
    <span class="absolute inset-0 flex items-center justify-center font-black" :style="{ fontSize: size / 4 + 'px' }">{{ Math.round(value) }}%</span>
  </div>
</template>
<script setup lang="ts">
withDefaults(defineProps<{ value: number; size?: number; color?: string }>(), { size: 64, color: '#16C784' })
</script>
