<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="mb-8">
        <h1 class="text-4xl font-black text-[var(--text-primary)] mb-2">Rentals</h1>
        <p class="text-[var(--text-muted)]">Find rentals and compare prices with AI estimates</p>
      </div>
      <ListingsGrid type="rental" />
    </div>
  </MainLayout>
</template>
<script setup lang="ts">
import { usePageMeta } from '@/composables/usePageMeta'
usePageMeta(() => ({ title: 'Rentals in North Cyprus', description: 'Homes and apartments to rent in North Cyprus, with AI checks on the asking rent.' }))
import MainLayout from '@/layouts/MainLayout.vue'
import ListingsGrid from '@/components/listings/ListingsGrid.vue'
</script>