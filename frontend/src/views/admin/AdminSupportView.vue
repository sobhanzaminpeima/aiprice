<template>
  <div class="p-8">
    <div class="mb-6">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Support <span class="gradient-text">Tickets</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Urgent first, then the ticket that has waited longest.</p>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-4 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <div class="flex flex-wrap items-center gap-2 mb-5">
      <button v-for="s in filters" :key="s.key" @click="setFilter(s.key)" class="px-3 py-1.5 rounded-lg text-xs font-semibold" :style="filter === s.key ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)' : 'background:var(--surface-overlay);color:var(--text-muted)'">
        {{ s.label }}<span v-if="s.count !== undefined" class="ml-1 opacity-70">{{ s.count }}</span>
      </button>
      <label class="ml-2 flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)"><input type="checkbox" v-model="mine" @change="load" /> Assigned to me</label>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- List -->
      <div class="lg:col-span-2 space-y-2">
        <p v-if="!tickets.length" class="text-sm py-10 text-center rounded-2xl" style="background:var(--bg-surface);color:var(--text-muted)">No tickets here.</p>
        <button v-for="t in tickets" :key="t.id" @click="open(t.id)" class="w-full text-left p-4 rounded-xl" :style="selected?.id === t.id ? 'background:rgba(var(--accent-teal-rgb),0.06);border:1px solid rgba(var(--accent-teal-rgb),0.25)' : 'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
          <div class="flex justify-between gap-2 mb-1">
            <span class="text-xs font-mono" style="color:var(--text-muted)">{{ t.ticket_number }}</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full uppercase font-bold" :style="priorityStyle(t.priority)">{{ t.priority }}</span>
          </div>
          <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ t.subject }}</p>
          <p class="text-xs truncate" style="color:var(--text-secondary)">{{ t.user?.name || t.guest_name }}<span v-if="!t.user_id"> (guest)</span> · {{ t.replies_count }} replies · {{ ago(t.updated_at) }}</p>
          <span class="inline-block mt-1 text-[10px] capitalize px-2 py-0.5 rounded" style="background:var(--surface-overlay);color:var(--text-muted)">{{ t.status.replace('_', ' ') }}</span>
        </button>
      </div>

      <!-- Detail -->
      <div class="lg:col-span-3">
        <div v-if="!selected" class="rounded-2xl p-10 text-center text-sm" style="background:var(--bg-surface);border:1px solid var(--border-subtle);color:var(--text-muted)">Select a ticket</div>
        <div v-else class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <p class="text-xs font-mono" style="color:var(--text-muted)">{{ selected.ticket_number }} · {{ selected.category || 'general' }}</p>
              <h2 class="text-lg font-bold" style="color:var(--text-primary)">{{ selected.subject }}</h2>
              <p class="text-xs" style="color:var(--text-secondary)">{{ selected.user?.name || selected.guest_name }} · {{ selected.user?.email || selected.guest_email }} · {{ selected.user?.role || 'guest via contact form — replies go by email' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <select :value="selected.status" @change="patch({ status: ($event.target as HTMLSelectElement).value })" class="px-2 py-1.5 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)">
                <option v-for="s in ['open', 'in_progress', 'waiting', 'resolved', 'closed']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
              </select>
              <select :value="selected.priority" @change="patch({ priority: ($event.target as HTMLSelectElement).value })" class="px-2 py-1.5 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)">
                <option v-for="p in ['low', 'medium', 'high', 'urgent']" :key="p" :value="p">{{ p }}</option>
              </select>
              <select :value="selected.assigned_to || ''" @change="patch({ assigned_to: ($event.target as HTMLSelectElement).value || null })" class="px-2 py-1.5 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)">
                <option value="">Unassigned</option>
                <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </div>
          </div>

          <div class="space-y-3 max-h-[420px] overflow-y-auto mb-4 pr-1">
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
              <p class="text-xs font-semibold mb-1" style="color:var(--text-primary)">{{ selected.user?.name || selected.guest_name }} <span class="font-normal" style="color:var(--text-muted)">· {{ ago(selected.created_at) }}</span></p>
              <p class="text-sm whitespace-pre-wrap" style="color:var(--text-secondary)">{{ selected.description }}</p>
            </div>
            <div v-for="r in selected.replies" :key="r.id" class="p-3 rounded-xl" :style="r.is_staff ? 'background:rgba(var(--accent-teal-rgb),0.06);margin-left:2rem' : 'background:var(--surface-overlay-soft);margin-right:2rem'">
              <p class="text-xs font-semibold mb-1" :style="r.is_staff ? 'color:var(--accent-teal)' : 'color:var(--text-primary)'">{{ r.user?.name }}{{ r.is_staff ? ' (staff)' : '' }} <span class="font-normal" style="color:var(--text-muted)">· {{ ago(r.created_at) }}</span></p>
              <p class="text-sm whitespace-pre-wrap" style="color:var(--text-secondary)">{{ r.body }}</p>
            </div>
          </div>

          <form v-if="selected.status !== 'closed'" @submit.prevent="reply" class="space-y-2">
            <textarea v-model="replyBody" rows="4" required placeholder="Write a reply — the customer is emailed" class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
            <div class="flex items-center gap-2">
              <select v-model="replyStatus" class="px-2 py-2 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)">
                <option value="waiting">Send &amp; wait for customer</option>
                <option value="resolved">Send &amp; mark resolved</option>
                <option value="in_progress">Send &amp; keep in progress</option>
              </select>
              <button type="submit" :disabled="sending" class="ml-auto px-5 py-2 rounded-xl text-xs font-bold disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ sending ? 'Sending…' : 'Send reply' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const route = useRoute()
const error = ref('')
const tickets = ref<any[]>([])
const counts = ref<Record<string, number>>({})
const agents = ref<any[]>([])
const selected = ref<any>(null)
const filter = ref('active')
const mine = ref(false)
const replyBody = ref('')
const replyStatus = ref('waiting')
const sending = ref(false)

const filters = computed(() => [
  { key: 'active', label: 'Active', count: (counts.value.open ?? 0) + (counts.value.in_progress ?? 0) + (counts.value.waiting ?? 0) },
  { key: 'open', label: 'Open', count: counts.value.open ?? 0 },
  { key: 'waiting', label: 'Waiting on customer', count: counts.value.waiting ?? 0 },
  { key: 'resolved', label: 'Resolved', count: counts.value.resolved ?? 0 },
  { key: 'all', label: 'All' },
])

async function load() {
  try {
    const r = await client.get('/admin/support/tickets', { params: { status: filter.value, mine: mine.value ? 1 : undefined } })
    tickets.value = r.data.tickets.data
    counts.value = r.data.counts
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load tickets.')
  }
}

function setFilter(k: string) { filter.value = k; load() }

async function open(id: string) {
  try {
    selected.value = (await client.get(`/admin/support/tickets/${id}`)).data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not open the ticket.')
  }
}

async function patch(changes: Record<string, unknown>) {
  try {
    await client.put(`/admin/support/tickets/${selected.value.id}`, changes)
    await Promise.all([open(selected.value.id), load()])
  } catch (e) {
    error.value = apiErrorMessage(e, 'Update failed.')
  }
}

async function reply() {
  sending.value = true
  try {
    await client.post(`/admin/support/tickets/${selected.value.id}/reply`, { body: replyBody.value, status: replyStatus.value })
    replyBody.value = ''
    await Promise.all([open(selected.value.id), load()])
  } catch (e) {
    error.value = apiErrorMessage(e, 'Reply failed.')
  } finally {
    sending.value = false
  }
}

function priorityStyle(p: string) {
  return ({
    urgent: 'background:rgba(var(--danger-rgb),0.15);color:var(--danger)',
    high: 'background:rgba(var(--warning-rgb),0.15);color:var(--warning)',
    medium: 'background:rgba(var(--accent-cyan-rgb),0.12);color:var(--accent-cyan)',
  } as Record<string, string>)[p] ?? 'background:var(--surface-overlay);color:var(--text-muted)'
}

function ago(iso: string) {
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (s < 3600) return `${Math.max(1, Math.floor(s / 60))}m ago`
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`
  return `${Math.floor(s / 86400)}d ago`
}

onMounted(async () => {
  await load()
  client.get('/admin/support/agents').then((r) => (agents.value = r.data)).catch(() => {})
  if (route.query.ticket) open(String(route.query.ticket))
})
</script>
