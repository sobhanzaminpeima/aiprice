<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">My <span class="gradient-text">Listings</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">{{ listings.length }} listings total</p>
      </div>
      <RouterLink to="/dashboard/listings/new" class="px-5 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ New Listing</RouterLink>
    </div>
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="h-20 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>
    <div v-else-if="listings.length===0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p class="text-4xl mb-3">📋</p>
      <p class="font-semibold mb-4" style="color:var(--text-primary)">No listings yet</p>
      <RouterLink to="/dashboard/listings/new" class="px-5 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Create First Listing</RouterLink>
    </div>
    <div v-else class="space-y-3">
      <div v-for="l in listings" :key="l.id" class="rounded-2xl p-5 flex items-center justify-between flex-wrap gap-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0" style="background:var(--surface-overlay)">
            <img v-if="l.thumbnail" :src="l.thumbnail" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center text-xl">{{ icon(l.type) }}</div>
          </div>
          <div>
            <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ l.title }}</p>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ l.listing_number }} &middot; {{ l.city }} &middot; Views: {{ l.view_count||0 }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <span class="font-bold text-sm" style="color:var(--accent-teal)">{{ fmt(l.price, l.currency) }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full capitalize" :style="sty(l.status)">{{ l.status }}</span>
          <div class="flex gap-2">
            <RouterLink :to="'/listings/'+l.slug" class="px-3 py-1.5 text-xs rounded-lg" style="background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)">View</RouterLink>
            <RouterLink :to="'/dashboard/listings/'+l.id+'/edit'" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</RouterLink>
            <button @click="del(l.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
const listings = ref<any[]>([])
const loading = ref(true)
onMounted(async () => { try { const r = await client.get('/my-listings'); listings.value = r.data.data || [] } catch {} finally { loading.value = false } })
async function del(id:string) { if (!confirm('Delete?')) return; await client.delete('/listings/'+id); listings.value = listings.value.filter(l=>l.id!==id) }
function fmt(v:number, cur='GBP') { return new Intl.NumberFormat('en-GB',{style:'currency',currency:cur||'GBP',maximumFractionDigits:0}).format(v) }
function sty(s:string) { const m:Record<string,string>={active:'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)',pending:'background:rgba(var(--warning-rgb),0.1);color:var(--warning)',rejected:'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'}; return m[s]||'background:var(--surface-overlay);color:var(--text-muted)' }
function icon(t:string) { return ({vehicle:'🚗',property:'🏠',rental:'🔑'} as any)[t]||'📋' }
</script>
