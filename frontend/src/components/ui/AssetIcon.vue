<template>
  <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
    <path v-if="type === 'vehicle'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :stroke="color"
      d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6" />
    <path v-else-if="type === 'property'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :stroke="color"
      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    <path v-else-if="type === 'rental'" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :stroke="color"
      d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1" />
    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :stroke="color"
      d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
  </svg>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ type: 'vehicle' | 'property' | 'rental' | 'all'; size?: number; color?: string }>(), {
  size: 16,
  color: 'currentColor',
})
</script>
