<template>
  <div class="p-4 md:p-8 max-w-5xl">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">AI <span class="gradient-text">Status</span></h1>
    <p class="text-sm mb-8" style="color:var(--text-muted)">
      How the AI price check is working right now. Price estimates are computed from comparable listings
      (same currency, similar model/year or property type/size). OpenAI is only used to write the short summary sentence.
    </p>

    <div v-if="error" class="mb-6 p-4 rounded-xl text-sm" role="alert" style="background:rgba(var(--danger-rgb),0.08);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.2)">{{ error }}</div>

    <div v-if="status" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="c in cards" :key="c.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black" :style="{ color: c.color }">{{ c.value }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">{{ c.label }}</p>
      </div>
    </div>
    <div v-else-if="!error" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="i in 8" :key="i" class="h-24 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>

    <div v-if="status" class="grid md:grid-cols-2 gap-6">
      <section class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">Configuration</h2>
        <ul class="space-y-3 text-sm">
          <li class="flex justify-between gap-4"><span style="color:var(--text-secondary)">OpenAI key (summary text)</span>
            <span :style="{ color: status.openai_configured ? 'var(--success)' : 'var(--warning)' }">{{ status.openai_configured ? 'Configured · ' + status.openai_model : 'Not set: rule-based summaries used' }}</span></li>
          <li class="flex justify-between gap-4"><span style="color:var(--text-secondary)">Queue</span>
            <span :style="{ color: ['sync', 'null'].includes(status.queue_connection) ? 'var(--warning)' : 'var(--success)' }">{{ status.queue_connection }}</span></li>
          <li class="flex justify-between gap-4"><span style="color:var(--text-secondary)">Jobs waiting / failed</span>
            <span :style="{ color: (status.failed_jobs ?? 0) > 0 ? 'var(--danger)' : 'var(--text-primary)' }">{{ status.pending_jobs ?? '—' }} / {{ status.failed_jobs ?? '—' }}</span></li>
          <li class="flex justify-between gap-4"><span style="color:var(--text-secondary)">Last listing scored</span>
            <span style="color:var(--text-primary)">{{ fmt(status.last_scored_at) }}</span></li>
        </ul>
        <p v-if="(status.pending_jobs ?? 0) > 20" class="text-xs mt-4" style="color:var(--warning)">Many jobs are waiting. Check that the queue worker / cron is running on the server.</p>

        <h3 class="font-semibold text-sm mt-6 mb-2" style="color:var(--text-primary)">Market data sources</h3>
        <p v-if="!status.market_sources.length" class="text-xs" style="color:var(--text-muted)">None configured. Estimates use AIPrice.app listings only.</p>
        <ul class="space-y-1 text-xs">
          <li v-for="s in status.market_sources" :key="s.name" class="flex justify-between" style="color:var(--text-secondary)">
            <span>{{ s.name }} <span v-if="!s.is_active" style="color:var(--text-muted)">(paused)</span></span>
            <span>last import: {{ fmt(s.last_scraped_at) }}</span>
          </li>
        </ul>
      </section>

      <section class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-2" style="color:var(--text-primary)">Re-run price analysis</h2>
        <p class="text-xs mb-4" style="color:var(--text-muted)">Queues listings for scoring. Run "all" once after deploying a new pricing model.</p>
        <div class="flex flex-wrap gap-2">
          <button class="btn" :disabled="busy" @click="rescore(false)">Score unscored listings</button>
          <button class="btn btn-outline-admin" :disabled="busy" @click="rescore(true)">Re-score all active</button>
        </div>
        <p v-if="rescoreMsg" class="text-xs mt-3" role="status" style="color:var(--success)">{{ rescoreMsg }}</p>

        <h2 class="font-bold mt-8 mb-2" style="color:var(--text-primary)">Test the OpenAI connection</h2>
        <form class="flex gap-2" @submit.prevent="testLlm">
          <label class="sr-only" for="llm-prompt">Test prompt</label>
          <input id="llm-prompt" v-model="prompt" maxlength="500" class="flex-1 px-3 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <button class="btn" :disabled="busy || !prompt">Send</button>
        </form>
        <p v-if="testReply" class="text-xs mt-3 p-3 rounded-xl" style="background:var(--surface-overlay);color:var(--text-secondary)">{{ testReply }}</p>
        <p v-if="testError" class="text-xs mt-3" role="alert" style="color:var(--danger)">{{ testError }}</p>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

interface AiStatus {
  openai_configured: boolean
  openai_model: string
  queue_connection: string
  active_listings: number
  with_estimate: number
  insufficient_data: number
  never_scored: number
  last_scored_at: string | null
  pending_jobs: number | null
  failed_jobs: number | null
  market_rows: number
  market_sources: { name: string; is_active: boolean; last_scraped_at: string | null }[]
}

const status = ref<AiStatus | null>(null)
const error = ref('')
const busy = ref(false)
const rescoreMsg = ref('')
const prompt = ref('Reply with the single word: OK')
const testReply = ref('')
const testError = ref('')

const cards = computed(() => {
  const s = status.value
  if (!s) return []
  return [
    { label: 'Active listings', value: s.active_listings, color: 'var(--text-primary)' },
    { label: 'With a price estimate', value: s.with_estimate, color: 'var(--success)' },
    { label: 'Not enough comparables', value: s.insufficient_data, color: 'var(--warning)' },
    { label: 'Never scored', value: s.never_scored, color: s.never_scored ? 'var(--danger)' : 'var(--text-primary)' },
    { label: 'Market data rows', value: s.market_rows, color: 'var(--accent-cyan)' },
  ]
})

function fmt(d: string | null) {
  return d ? new Date(d).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'never'
}

async function load() {
  error.value = ''
  try {
    status.value = (await client.get('/admin/ai/status')).data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load AI status.')
  }
}

async function rescore(all: boolean) {
  busy.value = true
  rescoreMsg.value = ''
  try {
    const { data } = await client.post('/admin/listings/batch-analyze', null, { params: all ? { all: 1 } : {} })
    rescoreMsg.value = data.message
    load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not queue the analysis.')
  } finally {
    busy.value = false
  }
}

async function testLlm() {
  busy.value = true
  testReply.value = ''
  testError.value = ''
  try {
    testReply.value = (await client.post('/admin/llm/test', { prompt: prompt.value, provider: 'openai' })).data.response
  } catch (e) {
    testError.value = apiErrorMessage(e, 'The test call failed.')
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.btn { padding:8px 14px;border-radius:12px;font-size:13px;font-weight:700;border:none;cursor:pointer;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-teal));color:var(--on-accent) }
.btn:disabled { opacity:.55;cursor:wait }
.btn-outline-admin { background:transparent;color:var(--accent-cyan);border:1px solid var(--border-cyan) }
</style>
