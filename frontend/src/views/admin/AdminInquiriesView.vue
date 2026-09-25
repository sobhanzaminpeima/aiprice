<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Buyer <span class="gradient-text">Inquiries</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">The only place both the buyer's and the owner's contact details appear together. Relay contact manually, then mark as contacted.</p>
    </div>

    <div class="flex gap-2 mb-6">
      <button v-for="s in ['all','pending','contacted','closed']" :key="s" @click="statusFilter=s; load()"
        class="px-4 py-2 rounded-xl text-xs font-semibold capitalize transition-all"
        :style="statusFilter===s?'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)':'background:var(--surface-overlay);color:var(--text-muted);border:1px solid transparent'">{{ s }}</button>
    </div>

    <div v-if="loading" class="space-y-3"><div v-for="i in 4" :key="i" class="h-32 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div></div>
    <div v-else-if="inquiries.length===0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p class="text-4xl mb-3">📭</p>
      <p class="font-semibold" style="color:var(--text-primary)">No inquiries</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="inq in inquiries" :key="inq.id" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <p class="text-xs" style="color:var(--text-muted)">Listing</p>
            <RouterLink :to="`/listings/${inq.listing?.slug}`" target="_blank" class="text-sm font-semibold hover:underline" style="color:var(--accent-cyan)">{{ inq.listing?.title }}</RouterLink>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ inq.listing?.price }} {{ inq.listing?.currency }}</p>
          </div>
          <span class="text-xs px-3 py-1 rounded-full capitalize" :style="statusStyle(inq.status)">{{ inq.status }}</span>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="p-3 rounded-xl" style="background:rgba(86,216,255,0.06);border:1px solid rgba(86,216,255,0.15)">
            <p class="text-xs font-bold mb-1" style="color:var(--accent-cyan)">👤 Buyer (requester)</p>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ inq.buyer?.name }}</p>
            <p class="text-xs" style="color:var(--text-secondary)">{{ inq.buyer?.email }}</p>
            <p class="text-xs" style="color:var(--text-secondary)">{{ inq.buyer?.phone || 'No phone on file' }}</p>
          </div>
          <div class="p-3 rounded-xl" style="background:rgba(111,255,210,0.06);border:1px solid rgba(111,255,210,0.15)">
            <p class="text-xs font-bold mb-1" style="color:var(--accent-teal)">🏠 Owner</p>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ inq.listing?.user?.name }}</p>
            <p class="text-xs" style="color:var(--text-secondary)">{{ inq.listing?.user?.email }}</p>
            <p class="text-xs" style="color:var(--text-secondary)">{{ inq.listing?.user?.phone || 'No phone on file' }}</p>
          </div>
        </div>

        <div class="p-3 rounded-xl mb-4" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
          <p class="text-xs mb-1" style="color:var(--text-muted)">Message from buyer</p>
          <p class="text-sm" style="color:var(--text-secondary)">{{ inq.message }}</p>
        </div>

        <div class="flex items-center gap-2">
          <button v-if="inq.status!=='contacted'" @click="setStatus(inq,'contacted')" class="text-xs px-3 py-1.5 rounded-lg" style="background:rgba(86,216,255,0.1);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.2)">Mark Contacted</button>
          <button v-if="inq.status!=='closed'" @click="setStatus(inq,'closed')" class="text-xs px-3 py-1.5 rounded-lg" style="background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)">Close</button>
          <span class="text-xs ml-auto" style="color:var(--text-muted)">{{ fmtDate(inq.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'

const inquiries = ref<any[]>([])
const loading = ref(true)
const statusFilter = ref('all')

async function load() {
  loading.value = true
  try {
    const params = statusFilter.value !== 'all' ? { status: statusFilter.value } : {}
    const r = await client.get('/admin/inquiries', { params })
    inquiries.value = r.data.data || r.data || []
  } catch {} finally { loading.value = false }
}
onMounted(load)

async function setStatus(inq: any, status: string) {
  try {
    await client.put(`/admin/inquiries/${inq.id}`, { status })
    inq.status = status
  } catch {}
}

function fmtDate(d: string) { return d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '' }
function statusStyle(s: string) {
  if (s === 'contacted') return 'background:rgba(86,216,255,0.1);color:var(--accent-cyan)'
  if (s === 'closed') return 'background:rgba(111,255,210,0.1);color:var(--accent-teal)'
  return 'background:rgba(167,139,250,0.1);color:var(--accent-violet)'
}
</script>
