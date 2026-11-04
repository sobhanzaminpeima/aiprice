<template>
  <MainLayout>
    <div class="max-w-xl mx-auto px-4 py-28 text-center">
      <p class="text-7xl font-black mb-4 gradient-text">404</p>
      <h1 class="text-2xl font-black mb-3" style="color:var(--text-primary)">This page doesn't exist</h1>
      <p class="mb-10" style="color:var(--text-muted)">The link may be old, or the listing may have been sold or removed.</p>
      <div class="flex flex-wrap gap-3 justify-center">
        <RouterLink to="/" class="px-6 py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Go home</RouterLink>
        <RouterLink to="/cars" class="px-6 py-3 rounded-xl text-sm font-semibold" style="color:var(--text-primary);border:1px solid var(--border-strong)">Browse cars</RouterLink>
        <RouterLink to="/properties" class="px-6 py-3 rounded-xl text-sm font-semibold" style="color:var(--text-primary);border:1px solid var(--border-strong)">Browse properties</RouterLink>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import { usePageMeta } from '@/composables/usePageMeta'

usePageMeta(() => ({ title: 'Page not found', noindex: true }))
</script>
