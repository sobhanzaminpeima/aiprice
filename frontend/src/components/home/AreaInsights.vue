<template>
  <div v-if="areas.length" class="glass-areas rounded-3xl p-5 md:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h3 class="font-black text-lg" style="color:#fff">{{ $t('Explore the market across North Cyprus') }}</h3>
        <p class="text-xs" style="color:#a9bdd3">{{ $t('Where listings are most active and what a typical asking price is in each town.') }}</p>
      </div>
      <div class="flex items-center gap-3 text-[11px]" style="color:#a9bdd3" aria-hidden="true">
        <span v-for="d in legend" :key="d.k" class="flex items-center gap-1"><i class="w-2 h-2 rounded-full inline-block" :style="{ background: d.c }"></i>{{ $t(d.l) }}</span>
      </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <RouterLink v-for="a in areas" :key="a.city" :to="{ path: '/search', query: { city: a.city } }" class="area-card rounded-2xl p-4 block transition hover:-translate-y-0.5">
        <div class="flex items-center justify-between mb-1">
          <span class="font-bold text-sm" style="color:#fff">{{ a.city }}</span>
          <i class="w-2.5 h-2.5 rounded-full" :style="{ background: color(a.density) }" :title="$t(a.density === 'high' ? 'Very active' : a.density === 'medium' ? 'Active' : 'Fewer listings')"></i>
        </div>
        <p class="text-[11px]" style="color:#a9bdd3">{{ $t('Typical asking price') }}</p>
        <p class="text-lg font-black" style="color:#4fe0b0">{{ money(a.median_price, a.currency) }}</p>
        <p class="text-[11px] mt-1" :style="{ color: a.change_90d == null ? '#7f93ab' : a.change_90d >= 0 ? '#fbbf24' : '#4fe0b0' }">
          <template v-if="a.change_90d != null">{{ a.change_90d >= 0 ? '↑ +' : '↓ ' }}{{ a.change_90d }}% · {{ $t('90 days') }}</template>
          <template v-else>{{ $t('Change shows once there is enough data') }}</template>
        </p>
        <p class="text-[11px]" style="color:#7f93ab">{{ $t('{n} listings', { n: a.listings }) }}</p>
      </RouterLink>
    </div>
  </div>
</template>
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { locale } from '@/i18n'

interface Area { city: string; listings: number; median_price: number; currency: string; change_90d: number | null; density: 'high' | 'medium' | 'low' }
const areas = ref<Area[]>([])
const legend = [{ k: 'h', c: '#16C784', l: 'Very active' }, { k: 'm', c: '#fbbf24', l: 'Active' }, { k: 'l', c: '#12C5E8', l: 'Fewer listings' }]
const color = (d: string) => (d === 'high' ? '#16C784' : d === 'medium' ? '#fbbf24' : '#12C5E8')
const money = (v: number, c: string) => new Intl.NumberFormat(locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB', { style: 'currency', currency: c, maximumFractionDigits: 0 }).format(v)
onMounted(async () => { try { areas.value = (await client.get('/market/areas')).data } catch { /* optional */ } })
</script>
<style scoped>
.glass-areas { background: linear-gradient(150deg, #0a2540, #08304f 60%, #0a3b52); border: 1px solid rgba(120,190,255,.22); box-shadow: 0 18px 48px rgba(2,10,22,.28); }
.area-card { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); text-decoration: none; }
</style>
