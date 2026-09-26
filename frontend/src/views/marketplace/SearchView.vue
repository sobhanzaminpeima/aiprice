<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="mb-8 flex items-end justify-between flex-wrap gap-4">
        <div>
          <h1 class="text-4xl font-black mb-2" style="color:var(--text-primary)">
            <span v-if="searchType === 'vehicle'">Cars</span>
            <span v-else-if="searchType === 'property'">Properties</span>
            <span v-else>Search Results</span>
          </h1>
          <p v-if="searchQuery" style="color:var(--text-muted);font-size:14px">Results for: "<span style="color:var(--accent-teal)">{{ searchQuery }}</span>"
            <span v-if="searchCity"> · <span style="color:var(--accent-violet)">{{ searchCity }}</span></span>
          </p>
        </div>
        <div class="flex p-1 rounded-xl" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
          <button @click="view = 'grid'" class="px-4 py-2 rounded-lg text-xs font-bold transition"
            :style="view === 'grid' ? 'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)' : 'color:var(--text-muted)'">▦ Grid</button>
          <button @click="view = 'map'" class="px-4 py-2 rounded-lg text-xs font-bold transition"
            :style="view === 'map' ? 'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)' : 'color:var(--text-muted)'">🗺 Map</button>
        </div>
      </div>

      <ListingsMap v-if="view === 'map'" :height="600" :initial-type="searchType" />
      <ListingsGrid
        v-else
        :initial-search="searchQuery"
        :initial-type="searchType"
        :initial-city="searchCity"
        :initial-mode="searchMode"
        :initial-budget-max="searchBudgetMax"
        :key="routeKey"
      />
    </div>
  </MainLayout>
</template>
<script setup lang="ts">
import { usePageMeta } from '@/composables/usePageMeta'
usePageMeta(() => ({ title: 'Search listings', description: 'Search cars and property across North Cyprus.' }))
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ListingsGrid from '@/components/listings/ListingsGrid.vue'
import ListingsMap from '@/components/listings/ListingsMap.vue'

const route = useRoute()
const searchQuery = computed(() => (route.query.q as string) || '')
const searchType  = computed(() => (route.query.type as string) || '')
const searchCity  = computed(() => (route.query.city as string) || '')
const searchMode  = computed(() => (route.query.mode as string) || '')
const searchBudgetMax = computed(() => (route.query.budget_max as string) || '')
const routeKey    = computed(() => route.fullPath)

const view = ref<'grid' | 'map'>(route.query.view === 'map' ? 'map' : 'grid')
watch(() => route.query.view, (v) => { view.value = v === 'map' ? 'map' : 'grid' })
</script>
