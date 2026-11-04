<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Dashboard <span class="gradient-text">Overview</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Welcome back, {{ auth.user?.name }}</p>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="s in stats" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" :style="{color:s.color}">{{ s.value }}</p>
        <p class="text-xs" style="color:var(--text-muted)">{{ s.label }}</p>
      </div>
    </div>
    <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">Recent Listings</h2>
        <RouterLink to="/dashboard/listings/new" class="px-4 py-2 text-xs font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ New Listing</RouterLink>
      </div>
      <div v-if="loading" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-14 rounded-xl animate-pulse" style="background:var(--surface-overlay)"></div>
      </div>
      <div v-else-if="listings.length===0" class="text-center py-10">
        <p class="text-4xl mb-3">📋</p>
        <p class="text-sm mb-4" style="color:var(--text-muted)">No listings yet</p>
        <RouterLink to="/dashboard/listings/new" class="px-4 py-2 text-xs font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Create First Listing</RouterLink>
      </div>
      <div v-else class="space-y-2">
        <div v-for="l in listings.slice(0,5)" :key="l.id" class="flex items-center justify-between p-4 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
          <div>
            <p class="font-medium text-sm" style="color:var(--text-primary)">{{ l.title }}</p>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ l.type }} &middot; {{ l.listing_number }}</p>
          </div>
          <div class="flex items-center gap-4">
            <span class="font-semibold text-sm" style="color:var(--accent-teal)">{{ formatPrice(l.price, l.currency) }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full capitalize" :style="statusStyle(l.status)">{{ l.status }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
const listings = ref<any[]>([])
const loading = ref(true)
onMounted(async () => { try { const r = await client.get('/my-listings'); listings.value = r.data.data || [] } catch {} finally { loading.value = false } })
const stats = computed(() => [
  { label: 'Total Listings', value: listings.value.length, color: 'var(--accent-teal)' },
  { label: 'Active', value: listings.value.filter((l:any) => l.status === 'active').length, color: 'var(--accent-cyan)' },
  { label: 'Pending Review', value: listings.value.filter((l:any) => l.status === 'pending').length, color: 'var(--warning)' },
  { label: 'Total Views', value: listings.value.reduce((s:number,l:any) => s+(l.view_count||0), 0), color: 'var(--accent-violet)' },
])
function formatPrice(v:number, cur='GBP') { return new Intl.NumberFormat('en-GB',{style:'currency',currency:cur||'GBP',maximumFractionDigits:0}).format(v) }
function statusStyle(s:string) { const m:Record<string,string>={active:'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)',pending:'background:rgba(var(--warning-rgb),0.1);color:var(--warning)',rejected:'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'}; return m[s]||'background:var(--surface-overlay);color:var(--text-muted)' }
</script>