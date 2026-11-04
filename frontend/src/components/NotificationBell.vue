<template>
  <div class="relative" ref="root">
    <button @click="toggle" class="relative w-9 h-9 flex items-center justify-center rounded-lg" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)" :aria-label="`Notifications (${unread} unread)`">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-secondary)"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <span v-if="unread > 0" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold flex items-center justify-center" style="background:var(--danger);color:#fff">{{ unread > 99 ? '99+' : unread }}</span>
    </button>

    <div v-if="open" class="absolute z-50 w-80 rounded-2xl overflow-hidden shadow-2xl" :style="panelPosition" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--border-subtle)">
        <p class="text-sm font-bold" style="color:var(--text-primary)">Notifications</p>
        <button v-if="unread > 0" @click="markAll" class="text-xs" style="color:var(--accent-teal)">Mark all read</button>
      </div>
      <div class="max-h-96 overflow-y-auto">
        <p v-if="loading" class="px-4 py-6 text-xs text-center" style="color:var(--text-muted)">Loading…</p>
        <p v-else-if="items.length === 0" class="px-4 py-6 text-xs text-center" style="color:var(--text-muted)">You're all caught up.</p>
        <button v-for="n in items" :key="n.id" @click="openItem(n)" class="w-full text-left px-4 py-3 flex gap-3" :style="n.read_at ? '' : 'background:rgba(var(--accent-teal-rgb),0.04)'" style="border-bottom:1px solid var(--border-subtle)">
          <AppIcon :name="describe(n).icon" :size="18" class="shrink-0" />
          <span class="flex-1 min-w-0">
            <span class="block text-xs font-semibold" style="color:var(--text-primary)">{{ describe(n).title }}</span>
            <span v-if="describe(n).body" class="block text-xs truncate" style="color:var(--text-secondary)">{{ describe(n).body }}</span>
            <span class="block text-[10px] mt-0.5" style="color:var(--text-muted)">{{ ago(n.created_at) }}</span>
          </span>
          <span v-if="!n.read_at" class="w-2 h-2 rounded-full mt-1 shrink-0" style="background:var(--accent-teal)"></span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import client from '@/api/client'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = withDefaults(defineProps<{ align?: 'left' | 'right' | 'up' }>(), { align: 'right' })

const panelPosition = computed(() => ({
  left: 'top:44px;left:0',
  right: 'top:44px;right:0',
  up: 'bottom:44px;left:0',
}[props.align]))

const router = useRouter()
const root = ref<HTMLElement | null>(null)
const open = ref(false)
const loading = ref(false)
const unread = ref(0)
const items = ref<any[]>([])
let timer: number | undefined

interface Described { icon: string; title: string; body?: string; to?: string }

const kinds: Record<string, (d: any) => Described> = {
  listing_approved: (d) => ({ icon: 'check', title: 'Listing approved', body: d.title, to: '/dashboard/listings' }),
  listing_rejected: (d) => ({ icon: 'alert', title: 'Listing needs changes', body: d.reason || d.title, to: '/dashboard/listings' }),
  kyc_approved: () => ({ icon: 'shield', title: 'Identity verified', to: '/dashboard/kyc' }),
  kyc_rejected: (d) => ({ icon: 'shield', title: 'Identity check failed', body: d.reason, to: '/dashboard/kyc' }),
  new_inquiry: (d) => ({ icon: 'message', title: 'New buyer inquiry', body: d.listing_title, to: '/admin/inquiries' }),
  staff_alert: (d) => ({ icon: 'message', title: d.title, body: [d.from?.name, d.body].filter(Boolean).join(': '), to: '/admin/inbox' }),
  payment_succeeded: (d) => ({ icon: 'creditCard', title: 'Payment received', body: d.plan, to: '/dashboard/subscription' }),
  payment_failed: (d) => ({ icon: 'creditCard', title: 'Payment failed', body: d.plan, to: '/dashboard/subscription' }),
}

function describe(n: any): Described {
  const d = n.data ?? {}
  return kinds[d.kind]?.(d) ?? { icon: 'bell', title: d.title ?? 'Notification' }
}

function ago(iso: string) {
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (s < 60) return 'just now'
  if (s < 3600) return `${Math.floor(s / 60)}m ago`
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

async function refreshCount() {
  // Skip while the tab is hidden — no point polling a page nobody is looking at.
  if (document.hidden) return
  try {
    const r = await client.get('/notifications/unread-count')
    unread.value = r.data?.unread_count ?? 0
  } catch {
    /* transient — the next tick retries */
  }
}

async function load() {
  loading.value = true
  try {
    const r = await client.get('/notifications')
    items.value = r.data?.notifications?.data ?? []
    unread.value = r.data?.unread_count ?? 0
  } finally {
    loading.value = false
  }
}

function toggle() {
  open.value = !open.value
  if (open.value) load()
}

async function openItem(n: any) {
  if (!n.read_at) {
    const r = await client.post(`/notifications/${n.id}/read`)
    unread.value = r.data?.unread_count ?? unread.value
    n.read_at = new Date().toISOString()
  }
  const to = describe(n).to
  open.value = false
  if (to) router.push(to)
}

async function markAll() {
  await client.post('/notifications/read-all')
  unread.value = 0
  items.value = items.value.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() }))
}

function onClickOutside(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false
}

onMounted(() => {
  refreshCount()
  timer = window.setInterval(refreshCount, 60_000)
  document.addEventListener('click', onClickOutside)
  document.addEventListener('visibilitychange', refreshCount)
})

onBeforeUnmount(() => {
  window.clearInterval(timer)
  document.removeEventListener('click', onClickOutside)
  document.removeEventListener('visibilitychange', refreshCount)
})

defineExpose({ refreshCount })
</script>
