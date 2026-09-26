<template>
  <div class="flex flex-col gap-1">
    <span class="text-xs flex items-center gap-1.5" style="color:var(--text-muted)"><AppIcon v-if="icon" :name="icon" :size="14" /> {{ label }}</span>
    <span class="text-sm font-semibold" style="color:var(--text-primary)">{{ value }}</span>
  </div>
</template>

<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
defineProps<{ icon?: string; label: string; value: string | number | null | undefined }>()
</script>
