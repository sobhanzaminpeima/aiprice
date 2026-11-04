<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Notifications &amp; <span class="gradient-text">Announcements</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Message a group of users in-app, and optionally by email. Sending runs in the background.</p>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-4 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="s in statCards" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" :style="{ color: s.color }">{{ s.value }}</p>
        <p class="text-xs" style="color:var(--text-muted)">{{ s.label }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <form v-if="canSend" @submit.prevent="send" class="lg:col-span-2 rounded-2xl p-6 space-y-4 h-fit" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold" style="color:var(--text-primary)">New announcement</h2>
        <input v-model="form.title" required maxlength="150" placeholder="Title" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <textarea v-model="form.body" required rows="5" maxlength="2000" placeholder="Message" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
        <input v-model="form.link" type="url" placeholder="Link (optional) https://…" class="w-full px-3 py-2.5 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <div>
          <label class="block text-xs mb-1.5" style="color:var(--text-muted)">Audience</label>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="a in audiences" :key="a.key" type="button" @click="form.audience = a.key" class="px-3 py-2 rounded-lg text-xs font-semibold text-left" :style="form.audience === a.key ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.25)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid transparent'">{{ a.label }}</button>
          </div>
          <p class="text-xs mt-2" style="color:var(--text-muted)">{{ audienceSize === null ? 'Counting…' : `${audienceSize} active users will receive this.` }}</p>
        </div>
        <label class="flex items-center gap-2 text-xs" style="color:var(--text-secondary)"><input type="checkbox" v-model="form.send_email" /> Also send by email</label>
        <p v-if="sent" class="text-xs" style="color:var(--accent-teal)">Queued — it will appear in the history once delivered.</p>
        <button type="submit" :disabled="busy" class="w-full py-3 rounded-xl text-sm font-bold disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ busy ? 'Queuing…' : 'Send announcement' }}</button>
      </form>

      <div :class="canSend ? 'lg:col-span-3' : 'lg:col-span-5'" class="space-y-6">
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-4" style="color:var(--text-primary)">History</h2>
          <p v-if="!announcements.length" class="text-sm" style="color:var(--text-muted)">No announcements sent yet.</p>
          <div v-else class="space-y-3">
            <div v-for="a in announcements" :key="a.id" class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
              <div class="flex justify-between gap-3">
                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ a.title }}</p>
                <span class="text-xs capitalize" :style="a.status === 'sent' ? 'color:var(--accent-teal)' : a.status === 'failed' ? 'color:var(--danger)' : 'color:var(--warning)'">{{ a.status }}</span>
              </div>
              <p class="text-xs mt-1 line-clamp-2" style="color:var(--text-secondary)">{{ a.body }}</p>
              <p class="text-xs mt-1" style="color:var(--text-muted)">{{ a.audience }} · {{ a.recipients_count }} recipients · {{ a.channels.join(' + ') }} · {{ a.sender?.name }} · {{ fmt(a.sent_at || a.created_at) }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-4" style="color:var(--text-primary)">System notifications (30 days)</h2>
          <p v-if="!Object.keys(byKind).length" class="text-sm" style="color:var(--text-muted)">None sent yet.</p>
          <div v-else class="space-y-2">
            <div v-for="(n, kind) in byKind" :key="kind" class="flex justify-between text-xs">
              <span style="color:var(--text-secondary)">{{ String(kind).replace(/_/g, ' ') }}</span>
              <span class="font-bold" style="color:var(--text-primary)">{{ n }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canSend = computed(() => auth.can('manage_cms'))

const audiences = [
  { key: 'all', label: 'Everyone' },
  { key: 'buyers', label: 'Buyers & investors' },
  { key: 'sellers', label: 'Sellers, dealers, agencies' },
  { key: 'staff', label: 'Staff only' },
]

const error = ref('')
const busy = ref(false)
const sent = ref(false)
const stats = ref<any>({})
const byKind = ref<Record<string, number>>({})
const announcements = ref<any[]>([])
const audienceSize = ref<number | null>(null)
const form = ref({ title: '', body: '', link: '', audience: 'all', send_email: false })
let poll: number | undefined

const statCards = computed(() => [
  { label: 'Announcements (30d)', value: stats.value.sent_30d ?? 0, color: 'var(--accent-teal)' },
  { label: 'Recipients reached (30d)', value: stats.value.recipients_30d ?? 0, color: 'var(--accent-cyan)' },
  { label: 'All notifications (30d)', value: stats.value.notifications_30d ?? 0, color: 'var(--accent-violet)' },
  { label: 'Read rate (30d)', value: stats.value.read_rate_30d === null || stats.value.read_rate_30d === undefined ? '—' : `${stats.value.read_rate_30d}%`, color: 'var(--warning)' },
])

async function load() {
  const r = await client.get('/admin/announcements')
  stats.value = r.data.stats
  byKind.value = r.data.by_kind_30d
  announcements.value = r.data.announcements.data
  // Keep refreshing while anything is still going out.
  const pending = announcements.value.some((a) => a.status === 'queued' || a.status === 'sending')
  window.clearTimeout(poll)
  if (pending) poll = window.setTimeout(() => load().catch(() => {}), 4000)
}

async function countAudience() {
  if (!canSend.value) return
  audienceSize.value = null
  try {
    audienceSize.value = (await client.get('/admin/announcements/audience-size', { params: { audience: form.value.audience } })).data.count
  } catch { audienceSize.value = 0 }
}

async function send() {
  if (!window.confirm(`Send “${form.value.title}” to ${audienceSize.value ?? 'these'} users${form.value.send_email ? ' by email and in-app' : ' in-app'}?`)) return
  busy.value = true
  error.value = ''
  try {
    await client.post('/admin/announcements', { ...form.value, link: form.value.link || null })
    form.value = { title: '', body: '', link: '', audience: form.value.audience, send_email: false }
    sent.value = true
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not send the announcement.')
  } finally {
    busy.value = false
  }
}

function fmt(d: string) { return new Date(d).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }

watch(() => form.value.audience, countAudience)
onMounted(() => { load().catch((e) => (error.value = apiErrorMessage(e, 'Could not load notifications.'))); countAudience() })
onBeforeUnmount(() => window.clearTimeout(poll))
</script>
