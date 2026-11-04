<template>
  <div class="p-8">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Inquiries <span class="gradient-text">Inbox</span></h1>
    <p class="text-sm mb-8" style="color:var(--text-muted)">Messages buyers sent with “Send to seller”. Contact details appear only when the buyer chose to share them.</p>
    <div v-if="loading" class="space-y-3"><div v-for="i in 4" :key="i" class="h-20 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div></div>
    <div v-else-if="inquiries.length===0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p class="text-4xl mb-3" aria-hidden="true">💬</p>
      <p class="font-semibold" style="color:var(--text-primary)">No inquiries yet</p>
      <p class="text-sm mt-1" style="color:var(--text-muted)">Requests from interested buyers will appear here</p>
    </div>
    <div v-else class="space-y-3">
      <div v-for="m in inquiries" :key="m.id" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-start justify-between gap-4 mb-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm" style="background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)" aria-hidden="true">{{ (m.buyer?.name || '?').charAt(0).toUpperCase() }}</div>
            <div>
              <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ m.buyer?.name || 'A buyer' }}</p>
              <p class="text-xs" style="color:var(--text-muted)">Re: {{ m.listing?.title }}</p>
            </div>
          </div>
          <span class="text-xs px-2 py-1 rounded-full" :style="statusStyle(m.status)">{{ m.status }}</span>
        </div>
        <p class="text-sm pl-12" style="color:var(--text-secondary)">{{ m.message }}</p>
        <div v-if="m.buyer" class="pl-12 mt-3 flex flex-wrap gap-2 text-xs">
          <a :href="`mailto:${m.buyer.email}`" class="contact-chip">{{ m.buyer.email }}</a>
          <a v-if="m.buyer.phone" :href="`tel:${String(m.buyer.phone).replace(/[^\d+]/g, '')}`" class="contact-chip">{{ m.buyer.phone }}</a>
        </div>
        <p class="text-xs pl-12 mt-2" style="color:var(--text-muted)">{{ fmtDate(m.created_at) }}<span v-if="!m.buyer"> · The buyer did not share contact details; AIPrice support can relay a reply.</span></p>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'
const inquiries = ref<any[]>([]); const loading = ref(true)
onMounted(async () => { try { const r = await client.get('/my-listing-inquiries'); inquiries.value = r.data || [] } catch {} finally { loading.value = false } })
function fmtDate(d:string) { return d ? new Date(d).toLocaleDateString('en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : '' }
function statusStyle(s:string) {
  if (s === 'contacted') return 'background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)'
  if (s === 'closed') return 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)'
  return 'background:rgba(var(--accent-violet-rgb),0.1);color:var(--accent-violet)'
}
</script>
<style scoped>
.contact-chip { padding:4px 10px;border-radius:999px;border:1px solid var(--border-cyan);color:var(--accent-cyan);text-decoration:none;font-weight:600 }
</style>
