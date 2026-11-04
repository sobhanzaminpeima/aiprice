<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-24">
      <div class="text-center mb-14">
        <span class="ai-badge mb-4 inline-block">Live from our listings</span>
        <h1 class="text-4xl md:text-5xl font-black mb-4" style="color:var(--text-primary)">Market <span class="gradient-text">Intelligence</span></h1>
        <p class="text-lg max-w-2xl mx-auto" style="color:var(--text-muted)">Aggregated from every active listing on AIPrice.app and its AI scores. Updated continuously.</p>
      </div>

      <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <div v-for="i in 4" :key="i" class="h-28 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <p v-else-if="error" class="text-center text-sm" style="color:var(--danger)">{{ error }}</p>

      <template v-else-if="stats">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
          <div v-for="s in statCards" :key="s.label" class="rounded-2xl p-6 text-center" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <div class="text-3xl font-black mb-1" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="text-sm" style="color:var(--text-muted)">{{ s.label }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
          <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <h2 class="font-bold mb-1" style="color:var(--text-primary)">Market index</h2>
            <p class="text-xs mb-5" style="color:var(--text-muted)">Average of trust, demand and deal scores across active listings (0–100).</p>
            <div class="space-y-4">
              <div v-for="t in segments" :key="t.key" class="flex items-center gap-3">
                <span class="text-sm w-24 shrink-0" style="color:var(--text-secondary)">{{ t.label }}</span>
                <div class="flex-1 h-2 rounded-full overflow-hidden" style="background:var(--surface-overlay)">
                  <div class="h-full rounded-full" :style="{ width: (indexFor(t.key)?.index ?? 0) + '%', background: t.color }"></div>
                </div>
                <span class="text-sm font-semibold w-12 text-right" style="color:var(--text-primary)">{{ indexFor(t.key)?.index ?? '—' }}</span>
              </div>
            </div>
            <p v-if="!stats.indexes.some((i: any) => i.index !== null)" class="text-xs mt-4" style="color:var(--text-muted)">Scores appear once listings have been analysed.</p>
          </div>

          <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <h2 class="font-bold mb-1" style="color:var(--text-primary)">How asking prices compare</h2>
            <p class="text-xs mb-5" style="color:var(--text-muted)">AI verdict on each active listing against similar ones in the same city.</p>
            <p v-if="!verdictTotal" class="text-sm" style="color:var(--text-muted)">No verdicts yet.</p>
            <div v-else class="space-y-3">
              <div v-for="(n, key) in stats.price_verdicts" :key="key">
                <div class="flex justify-between text-sm mb-1">
                  <span class="capitalize" style="color:var(--text-secondary)">{{ String(key).replace(/_/g, ' ') }}</span>
                  <span style="color:var(--text-primary)">{{ Math.round((Number(n) / verdictTotal) * 100) }}%</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden" style="background:var(--surface-overlay)">
                  <div class="h-full rounded-full" :style="{ width: (Number(n) / verdictTotal) * 100 + '%', background: verdictColor(String(key)) }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl p-8 text-center" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.08),rgba(var(--accent-cyan-rgb),0.06));border:1px solid rgba(var(--accent-teal-rgb),0.2)">
          <p class="text-xl font-black mb-2" style="color:var(--text-primary)">See the verdict on a specific listing</p>
          <p class="text-sm mb-6" style="color:var(--text-muted)">Every car and property page shows its fair-price analysis.</p>
          <div class="flex flex-wrap gap-3 justify-center">
            <RouterLink to="/cars" class="px-6 py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Browse cars</RouterLink>
            <RouterLink to="/properties" class="px-6 py-3 rounded-xl text-sm font-semibold" style="color:var(--text-primary);border:1px solid var(--border-strong)">Browse properties</RouterLink>
          </div>
        </div>
      </template>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { usePageMeta } from '@/composables/usePageMeta'

const stats = ref<any>(null)
const loading = ref(true)
const error = ref('')

usePageMeta(() => ({
  title: 'Market intelligence',
  description: 'Live market indexes and price verdicts for cars and property in North Cyprus, computed from every active AIPrice.app listing.',
}))

const segments = [
  { key: 'vehicle', label: 'Cars', color: 'var(--accent-teal)' },
  { key: 'property', label: 'Property', color: 'var(--accent-cyan)' },
  { key: 'rental', label: 'Rentals', color: 'var(--accent-violet)' },
]

const indexFor = (type: string) => stats.value?.indexes?.find((i: any) => i.type === type)
const verdictTotal = computed(() => Object.values(stats.value?.price_verdicts ?? {}).reduce((s: number, n: any) => s + Number(n), 0))
const statCards = computed(() => [
  { label: 'Cars for sale', value: (stats.value?.vehicle_listings ?? 0).toLocaleString(), color: 'var(--accent-teal)' },
  { label: 'Properties', value: (stats.value?.property_listings ?? 0).toLocaleString(), color: 'var(--accent-cyan)' },
  { label: 'Rentals', value: (stats.value?.rental_listings ?? 0).toLocaleString(), color: 'var(--accent-violet)' },
  { label: 'AI-scored listings', value: (stats.value?.ai_scored_listings ?? 0).toLocaleString(), color: 'var(--warning)' },
])

function verdictColor(s: string) {
  if (s.includes('under') || s.includes('deal')) return 'var(--accent-teal)'
  if (s === 'fair') return 'var(--accent-cyan)'
  if (s.includes('slightly')) return 'var(--warning)'
  return 'var(--danger)'
}

onMounted(async () => {
  try {
    stats.value = (await client.get('/market/stats')).data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load market data.')
  } finally {
    loading.value = false
  }
})
</script>
