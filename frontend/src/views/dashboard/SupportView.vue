<template>
  <div class="p-8 max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Help &amp; <span class="gradient-text">Support</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Ask us anything — we reply by email and here.</p>
      </div>
      <button v-if="!creating" @click="creating = true; selected = null" class="px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">New ticket</button>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-4 text-sm" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>

    <form v-if="creating" @submit.prevent="create" class="rounded-2xl p-6 mb-6 space-y-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input v-model="form.subject" required maxlength="200" placeholder="Subject" class="md:col-span-2 px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <select v-model="form.category" class="px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
          <option value="account">Account</option>
          <option value="listing">My listing</option>
          <option value="payment">Payment &amp; billing</option>
          <option value="technical">Technical problem</option>
          <option value="report">Report a problem user</option>
          <option value="other">Other</option>
        </select>
      </div>
      <textarea v-model="form.description" required rows="5" maxlength="5000" placeholder="Describe what happened" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
      <div class="flex gap-2 justify-end">
        <button type="button" @click="creating = false" class="px-4 py-2 rounded-xl text-sm" style="color:var(--text-muted)">Cancel</button>
        <button type="submit" :disabled="busy" class="px-5 py-2 rounded-xl text-sm font-bold disabled:opacity-50" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ busy ? 'Sending…' : 'Submit' }}</button>
      </div>
    </form>

    <div v-if="selected" class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <button @click="selected = null" class="text-xs mb-3" style="color:var(--text-muted)">← All tickets</button>
      <p class="text-xs font-mono" style="color:var(--text-muted)">{{ selected.ticket_number }} · <span class="capitalize">{{ selected.status.replace('_', ' ') }}</span></p>
      <h2 class="text-lg font-bold mb-4" style="color:var(--text-primary)">{{ selected.subject }}</h2>
      <div class="space-y-3 mb-4">
        <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft);margin-left:2rem">
          <p class="text-xs mb-1" style="color:var(--text-muted)">You · {{ fmt(selected.created_at) }}</p>
          <p class="text-sm whitespace-pre-wrap" style="color:var(--text-secondary)">{{ selected.description }}</p>
        </div>
        <div v-for="r in selected.replies" :key="r.id" class="p-3 rounded-xl" :style="r.is_staff ? 'background:rgba(111,255,210,0.06);margin-right:2rem' : 'background:var(--surface-overlay-soft);margin-left:2rem'">
          <p class="text-xs mb-1" :style="r.is_staff ? 'color:var(--accent-teal)' : 'color:var(--text-muted)'">{{ r.is_staff ? 'AIPrice support' : 'You' }} · {{ fmt(r.created_at) }}</p>
          <p class="text-sm whitespace-pre-wrap" style="color:var(--text-secondary)">{{ r.body }}</p>
        </div>
      </div>
      <form v-if="selected.status !== 'closed'" @submit.prevent="reply" class="flex gap-2">
        <textarea v-model="replyBody" required rows="2" placeholder="Reply…" class="flex-1 px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
        <button type="submit" :disabled="busy" class="px-4 rounded-xl text-sm font-bold disabled:opacity-50" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Send</button>
      </form>
      <p v-else class="text-xs" style="color:var(--text-muted)">This ticket is closed. Open a new one if you still need help.</p>
    </div>

    <div v-else-if="!creating" class="space-y-2">
      <p v-if="!tickets.length" class="text-sm py-12 text-center rounded-2xl" style="background:var(--bg-surface);color:var(--text-muted)">You have no support tickets.</p>
      <button v-for="t in tickets" :key="t.id" @click="open(t.id)" class="w-full text-left p-4 rounded-xl flex items-center gap-3" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ t.subject }}</p>
          <p class="text-xs" style="color:var(--text-muted)">{{ t.ticket_number }} · {{ t.replies_count }} replies · updated {{ fmt(t.updated_at) }}</p>
        </div>
        <span class="text-xs px-2 py-0.5 rounded-full capitalize" :style="t.status === 'waiting' ? 'background:rgba(251,191,36,0.1);color:#fbbf24' : 'background:var(--surface-overlay);color:var(--text-muted)'">{{ t.status === 'waiting' ? 'reply needed' : t.status.replace('_', ' ') }}</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const route = useRoute()
const tickets = ref<any[]>([])
const selected = ref<any>(null)
const creating = ref(false)
const busy = ref(false)
const error = ref('')
const replyBody = ref('')
const form = ref({ subject: '', description: '', category: 'account' })

async function load() {
  try {
    tickets.value = (await client.get('/support-tickets')).data.data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load your tickets.')
  }
}

async function open(id: string) {
  try {
    selected.value = (await client.get(`/support-tickets/${id}`)).data
    creating.value = false
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not open the ticket.')
  }
}

async function create() {
  busy.value = true
  error.value = ''
  try {
    const r = await client.post('/support-tickets', form.value)
    form.value = { subject: '', description: '', category: 'account' }
    creating.value = false
    await load()
    await open(r.data.id)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not create the ticket.')
  } finally {
    busy.value = false
  }
}

async function reply() {
  busy.value = true
  try {
    await client.post(`/support-tickets/${selected.value.id}/reply`, { body: replyBody.value })
    replyBody.value = ''
    await open(selected.value.id)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not send your reply.')
  } finally {
    busy.value = false
  }
}

function fmt(d: string) { return new Date(d).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }

onMounted(async () => {
  await load()
  if (route.query.ticket) open(String(route.query.ticket))
})
</script>
