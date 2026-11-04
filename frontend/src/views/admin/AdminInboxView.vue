<template>
  <div class="p-4 md:p-8 max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Requests <span class="gradient-text">inbox</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">
          Every request from a user, with the full message and the sender's details: messages to sellers, the contact form,
          support tickets, new listings, identity checks and new accounts. A copy is also emailed to staff.
        </p>
      </div>
      <button class="btn-ghost" :disabled="!unreadTotal" @click="markAll">Mark all read</button>
    </div>

    <div class="flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Request type">
      <button v-for="c in categories" :key="c.value" role="tab" :aria-selected="category === c.value" class="tab"
        :class="{ 'tab--on': category === c.value }" @click="category = c.value">{{ c.label }}</button>
    </div>
    <label class="inline-flex items-center gap-2 text-xs mb-5" style="color:var(--text-secondary)">
      <input v-model="onlyUnread" type="checkbox" /> Unread only
    </label>

    <div v-if="error" class="p-4 rounded-xl text-sm mb-4" role="alert" style="background:rgba(var(--danger-rgb),.08);color:var(--danger)">{{ error }}</div>

    <div v-if="loading" class="space-y-3"><div v-for="i in 3" :key="i" class="h-32 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div></div>
    <div v-else-if="!items.length" class="text-center py-16 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p class="font-semibold" style="color:var(--text-primary)">No requests here yet</p>
      <p class="text-sm mt-1" style="color:var(--text-muted)">New requests appear here within a minute.</p>
    </div>

    <ul v-else class="space-y-3">
      <li v-for="n in items" :key="n.id" class="item" :class="{ 'item--unread': !n.read_at }">
        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
          <div class="min-w-0">
            <span class="cat" :data-cat="n.data.category">{{ labelFor(n.data.category) }}</span>
            <h2 class="font-bold mt-1" style="color:var(--text-primary)">{{ n.data.title }}</h2>
          </div>
          <span class="text-xs shrink-0" style="color:var(--text-muted)" :title="new Date(n.created_at).toLocaleString('en-GB')">{{ when(n.created_at) }}</span>
        </div>

        <dl v-if="hasFrom(n)" class="from">
          <div v-if="n.data.from?.name"><dt>From</dt><dd>{{ n.data.from.name }}</dd></div>
          <div v-if="n.data.from?.email"><dt>Email</dt><dd><a :href="'mailto:' + n.data.from.email">{{ n.data.from.email }}</a></dd></div>
          <div v-if="n.data.from?.phone"><dt>Phone</dt><dd><a :href="'tel:' + n.data.from.phone">{{ n.data.from.phone }}</a></dd></div>
        </dl>

        <p v-if="n.data.body" class="body">{{ n.data.body }}</p>

        <dl v-if="n.data.details && Object.keys(n.data.details).length" class="details">
          <div v-for="(v, k) in n.data.details" :key="k">
            <dt>{{ k }}</dt>
            <dd><a v-if="String(v).startsWith('http')" :href="String(v)" target="_blank" rel="noopener">{{ v }}</a><template v-else>{{ v }}</template></dd>
          </div>
        </dl>

        <div class="flex flex-wrap items-center gap-2 mt-3">
          <RouterLink v-if="n.data.link" :to="n.data.link" class="btn-ghost" @click="markRead(n)">Open</RouterLink>
          <button v-if="!n.read_at" class="btn-ghost" @click="markRead(n)">Mark read</button>
        </div>
      </li>
    </ul>

    <div v-if="lastPage > 1" class="flex items-center justify-center gap-3 mt-6 text-sm">
      <button class="btn-ghost" :disabled="page <= 1" @click="page--">Previous</button>
      <span style="color:var(--text-muted)">Page {{ page }} of {{ lastPage }}</span>
      <button class="btn-ghost" :disabled="page >= lastPage" @click="page++">Next</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

interface Alert {
  id: string
  read_at: string | null
  created_at: string
  data: {
    category: string
    title: string
    body?: string | null
    from?: { name?: string; email?: string; phone?: string }
    details?: Record<string, string>
    link?: string | null
  }
}

const categories = [
  { value: '', label: 'All' },
  { value: 'inquiry', label: 'Messages to sellers' },
  { value: 'contact', label: 'Contact form' },
  { value: 'support', label: 'Support tickets' },
  { value: 'support_reply', label: 'Ticket replies' },
  { value: 'listing', label: 'New listings' },
  { value: 'kyc', label: 'Identity checks' },
  { value: 'signup', label: 'New accounts' },
]
const labelFor = (c: string) => categories.find((x) => x.value === c)?.label ?? c

const items = ref<Alert[]>([])
const loading = ref(true)
const error = ref('')
const category = ref('')
const onlyUnread = ref(false)
const page = ref(1)
const lastPage = ref(1)
const unreadTotal = ref(0)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/notifications', {
      params: { kind: 'staff_alert', category: category.value || undefined, unread: onlyUnread.value ? 1 : undefined, page: page.value, per_page: 20 },
    })
    items.value = data.notifications.data
    lastPage.value = data.notifications.last_page
    unreadTotal.value = data.unread_count
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load the inbox.')
  } finally {
    loading.value = false
  }
}

async function markRead(n: Alert) {
  if (n.read_at) return
  n.read_at = new Date().toISOString()
  try { unreadTotal.value = (await client.post(`/notifications/${n.id}/read`)).data.unread_count } catch { /* ignore */ }
}

async function markAll() {
  await client.post('/notifications/read-all')
  load()
}

const hasFrom = (n: Alert) => !!(n.data.from && (n.data.from.name || n.data.from.email || n.data.from.phone))

function when(d: string) {
  const s = (Date.now() - new Date(d).getTime()) / 1000
  if (s < 60) return 'just now'
  if (s < 3600) return `${Math.floor(s / 60)} min ago`
  if (s < 86400) return `${Math.floor(s / 3600)} h ago`
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

watch([category, onlyUnread], () => { page.value = 1; load() })
watch(page, load)
onMounted(load)
</script>

<style scoped>
.tab { padding:7px 12px;border-radius:999px;font-size:12.5px;font-weight:600;border:1px solid var(--border-subtle);background:var(--bg-surface);color:var(--text-secondary);cursor:pointer }
.tab--on { background:var(--accent-cyan);border-color:var(--accent-cyan);color:#fff }
.item { padding:18px;border-radius:16px;background:var(--bg-surface);border:1px solid var(--border-subtle) }
.item--unread { border-left:4px solid var(--accent-cyan) }
.cat { display:inline-block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:2px 8px;border-radius:6px;background:var(--surface-overlay);color:var(--text-secondary) }
.cat[data-cat="inquiry"], .cat[data-cat="contact"] { background:rgba(var(--accent-cyan-rgb),.1);color:var(--accent-cyan) }
.cat[data-cat="listing"] { background:rgba(var(--accent-teal-rgb),.1);color:var(--accent-teal) }
.cat[data-cat="kyc"] { background:rgba(var(--warning-rgb),.1);color:var(--warning) }
.from, .details { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:6px 16px;font-size:13px;margin:8px 0 }
.from dt, .details dt { font-size:11px;color:var(--text-muted) }
.from dd, .details dd { color:var(--text-primary);word-break:break-word }
.from a, .details a { color:var(--accent-cyan) }
.body { white-space:pre-wrap;font-size:14px;line-height:1.6;color:var(--text-primary);padding:12px;border-radius:12px;background:var(--surface-overlay);border:1px solid var(--border-subtle) }
.btn-ghost { padding:7px 12px;border-radius:10px;font-size:12.5px;font-weight:700;border:1px solid var(--border-strong);background:var(--bg-surface);color:var(--text-primary);text-decoration:none;cursor:pointer }
.btn-ghost:disabled { opacity:.5;cursor:default }
</style>
