<template>
  <section class="px-4 pb-14 max-w-7xl mx-auto" :aria-labelledby="id">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <p class="text-xs font-bold tracking-wider uppercase mb-1" style="color:var(--accent-cyan)">{{ icon }} {{ $t(eyebrow) }}</p>
        <h2 :id="id" class="text-2xl font-black" style="color:var(--text-primary)">{{ $t(title) }}</h2>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <div class="flex gap-1 p-1 rounded-xl" role="tablist" style="background:var(--surface-overlay)">
          <button v-for="t in types" :key="t.value" role="tab" :aria-selected="type === t.value" class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
            :style="type === t.value ? 'background:var(--bg-card);color:var(--text-primary);box-shadow:0 1px 4px rgba(8,24,39,.1)' : 'color:var(--text-muted)'" @click="type = t.value">{{ $t(t.label) }}</button>
        </div>
        <RouterLink :to="viewAll" class="text-sm font-semibold" style="color:var(--accent-cyan)">{{ $t('View all') }} →</RouterLink>
      </div>
    </div>
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i" class="h-72 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>
    <div v-else-if="items.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <ListingCard v-for="l in items" :key="l.id" :listing="l" />
    </div>
    <p v-else class="rounded-2xl p-8 text-center text-sm" style="background:var(--bg-card);border:1px solid var(--border-subtle);color:var(--text-muted)">{{ $t('Nothing here yet.') }}</p>
  </section>
</template>
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import ListingCard from '@/components/listings/ListingCard.vue'
import type { Listing } from '@/types'

const props = defineProps<{ id: string; title: string; eyebrow: string; icon: string; sort: string; extra?: Record<string, string> }>()
const types = [{ value: '', label: 'All' }, { value: 'vehicle', label: 'Cars' }, { value: 'property', label: 'Properties' }, { value: 'rental', label: 'Rentals' }]
const type = ref('')
const items = ref<Listing[]>([])
const loading = ref(true)
const viewAll = computed(() => {
  const query = { ...(props.sort !== 'newest' ? { sort: props.sort } : {}), ...(props.extra ?? {}) }
  const path = type.value === 'vehicle' ? '/cars' : type.value === 'property' ? '/properties' : type.value === 'rental' ? '/rentals' : '/search'
  return { path, query: path === '/search' ? {} : query }
})
async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { sort: props.sort, per_page: 4, ...(props.extra ?? {}) }
    if (type.value) params.type = type.value
    items.value = (await client.get('/listings', { params })).data?.data ?? []
  } catch { items.value = [] } finally { loading.value = false }
}
onMounted(load)
watch(type, load)
</script>
