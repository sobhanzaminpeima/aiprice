<template>
  <nav :aria-label="$t('Breadcrumb')" class="flex flex-wrap items-center gap-1.5 text-xs" style="color:var(--text-muted)">
    <template v-for="(c, i) in crumbs" :key="i">
      <span v-if="i" aria-hidden="true">›</span>
      <RouterLink v-if="c.to" :to="c.to" class="hover:underline" style="color:var(--text-secondary)">{{ c.label }}</RouterLink>
      <span v-else aria-current="page" class="font-semibold truncate max-w-[16rem]" style="color:var(--text-primary)">{{ c.label }}</span>
    </template>
  </nav>
</template>
<script setup lang="ts">
import { RouterLink } from 'vue-router'
defineProps<{ crumbs: { label: string; to?: string }[] }>()
</script>
