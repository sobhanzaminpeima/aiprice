<template>
  <div class="flex flex-col gap-1">
    <span class="text-xs" style="color:var(--text-muted)">{{ icon }} {{ label }}</span>
    <span class="text-sm font-semibold" style="color:var(--text-primary)">{{ value }}</span>
  </div>
</template>

<script setup lang="ts">
defineProps<{ icon?: string; label: string; value: string | number | null | undefined }>()
</script>
