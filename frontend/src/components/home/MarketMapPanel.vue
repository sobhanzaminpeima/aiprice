<template>
  <section class="px-4 pb-16 max-w-7xl mx-auto" aria-labelledby="map-heading">
    <div class="panel rounded-3xl p-5 md:p-7 grid grid-cols-1 lg:grid-cols-12 gap-6">
      <div class="lg:col-span-3 flex flex-col justify-center">
        <span class="chip">◉ {{ $t('North Cyprus market intelligence') }}</span>
        <h2 id="map-heading" class="text-2xl font-black mt-3 mb-2" style="color:#fff">{{ $t('Explore the market across North Cyprus') }}</h2>
        <p class="text-sm mb-5" style="color:#b9cde2">{{ $t('See where listings are most active, compare typical prices by area and find opportunities with AI.') }}</p>
        <RouterLink to="/search?view=map" class="explore">{{ $t('Explore market map') }} →</RouterLink>
      </div>

      <div class="lg:col-span-6 map-dark rounded-2xl overflow-hidden">
        <ListingsMap :height="360" compact />
      </div>

      <div class="lg:col-span-3 legend rounded-2xl p-4">
        <p class="font-bold text-sm mb-3" style="color:#fff">{{ $t('Listing density') }}</p>
        <ul class="space-y-1.5 mb-4">
          <li v-for="d in legend" :key="d.l" class="flex items-center gap-2 text-xs" style="color:#cfe0f3"><i class="dot" :style="{ background: d.c }"></i>{{ $t(d.l) }}</li>
        </ul>
        <div v-if="areas.length" class="space-y-2 mb-4">
          <RouterLink v-for="a in areas.slice(0, 4)" :key="a.city" :to="{ path: '/search', query: { city: a.city } }" class="area flex items-center justify-between gap-2">
            <span class="flex items-center gap-2 min-w-0"><i class="dot" :style="{ background: color(a.density) }"></i><span class="truncate">{{ a.city }}</span></span>
            <span class="text-right shrink-0">
              <span class="block font-bold" style="color:#fff">{{ money(a.median_price, a.currency) }}</span>
              <span class="block text-[10px]" :style="{ color: a.change_90d == null ? '#7f93ab' : a.change_90d >= 0 ? '#fbbf24' : '#4fe0b0' }">{{ a.change_90d == null ? $t('{n} listings', { n: a.listings }) : (a.change_90d >= 0 ? '↑ +' : '↓ ') + a.change_90d + '%' }}</span>
            </span>
          </RouterLink>
        </div>
        <div class="pt-3" style="border-top:1px solid rgba(255,255,255,.12)">
          <p class="text-xs" style="color:#a9bdd3">{{ $t('Typical price change (last 90 days)') }}</p>
          <p v-if="pulse?.change_90d != null" class="text-3xl font-black" :style="{ color: pulse.change_90d >= 0 ? '#4fe0b0' : '#f87171' }">{{ pulse.change_90d >= 0 ? '↑ +' : '↓ ' }}{{ pulse.change_90d }}%</p>
          <p v-else class="text-sm font-semibold mt-1" style="color:#cfe0f3">{{ $t('Shows once there is enough data') }}</p>
          <p class="text-[11px]" style="color:#7f93ab">{{ $t('North Cyprus, all listings') }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import ListingsMap from '@/components/listings/ListingsMap.vue'

interface Area { city: string; listings: number; median_price: number; currency: string; change_90d: number | null; density: string }
const areas = ref<Area[]>([])
const pulse = ref<{ change_90d: number | null; listings: number } | null>(null)
const legend = [{ c: '#f87171', l: 'Very high' }, { c: '#fbbf24', l: 'High' }, { c: '#16C784', l: 'Medium' }, { c: '#12C5E8', l: 'Low' }]
const color = (d: string) => (d === 'high' ? '#f87171' : d === 'medium' ? '#fbbf24' : '#12C5E8')
const money = (v: number, c: string) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: c, notation: v >= 100000 ? 'compact' : 'standard', maximumFractionDigits: v >= 100000 ? 1 : 0 }).format(v)
onMounted(async () => {
  client.get('/market/areas').then((r) => (areas.value = r.data)).catch(() => {})
  client.get('/market/pulse').then((r) => (pulse.value = r.data)).catch(() => {})
})
</script>
<style scoped>
.panel { background: linear-gradient(150deg, #071b2e 0%, #0a2a47 55%, #0b3a58 100%); border: 1px solid rgba(120,190,255,.22); box-shadow: 0 18px 48px rgba(2,10,22,.3); }
.chip { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#4fd8f5;background:rgba(18,197,232,.1);border:1px solid rgba(18,197,232,.3);align-self:flex-start }
.explore { align-self:flex-start;padding:10px 16px;border-radius:12px;font-size:13px;font-weight:700;color:#fff;border:1px solid rgba(255,255,255,.35);text-decoration:none }
.explore:hover { background:rgba(255,255,255,.08) }
.legend { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); }
.dot { width:9px;height:9px;border-radius:999px;display:inline-block;flex-shrink:0 }
.area { padding:8px 10px;border-radius:10px;font-size:12px;color:#cfe0f3;background:rgba(255,255,255,.04);text-decoration:none }
.area:hover { background:rgba(255,255,255,.08) }
/* Dark map: invert the light OpenStreetMap tiles; markers keep their colours. */
.map-dark :deep(.leaflet-tile-pane) { filter: invert(1) hue-rotate(185deg) brightness(.82) contrast(.92) saturate(.55); }
.map-dark :deep(.leaflet-container), .map-dark :deep(.map-shell) { background: #0a2236 !important; }
</style>
