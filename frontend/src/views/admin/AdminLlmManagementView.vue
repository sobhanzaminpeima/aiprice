<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">LLM <span class="gradient-text">Management</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Configure AI models, monitor usage, and manage API keys</p>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="stat in stats" :key="stat.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" :style="'background:rgba('+stat.rgb+',0.1)'">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" :stroke="'rgb('+stat.rgb+')'" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" :d="stat.icon"/>
            </svg>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full" :style="'background:rgba('+stat.rgb+',0.08);color:rgb('+stat.rgb+')'">{{ stat.badge }}</span>
        </div>
        <p class="text-2xl font-black mb-0.5" :style="'color:rgb('+stat.rgb+')'">{{ stat.value }}</p>
        <p class="text-xs" style="color:var(--text-muted)">{{ stat.label }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Model Config -->
      <div class="lg:col-span-2 space-y-5">

        <!-- Active Model -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Active Model
          </h2>

          <div v-if="modelsLoading" class="text-xs mb-4" style="color:var(--text-muted)">Loading models...</div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
            <div v-for="model in models" :key="model.id"
              @click="selectedModel = model.id"
              class="p-4 rounded-xl cursor-pointer transition-all"
              :style="selectedModel === model.id
                ? 'background:rgba(111,255,210,0.08);border:1px solid rgba(111,255,210,0.3)'
                : 'background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)'">
              <div class="flex items-start justify-between mb-2">
                <div>
                  <p class="text-sm font-bold" style="color:var(--text-primary)">{{ model.name }}</p>
                  <p class="text-xs capitalize" style="color:var(--text-muted)">{{ model.provider }} · {{ model.model_id }}</p>
                </div>
                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5"
                  :style="selectedModel === model.id ? 'border-color:var(--accent-teal)' : 'border-color:rgba(255,255,255,0.2)'">
                  <div v-if="selectedModel === model.id" class="w-2 h-2 rounded-full" style="background:#6fffd2"></div>
                </div>
              </div>
              <div class="flex flex-wrap gap-1.5 mb-2">
                <span v-if="model.is_default" class="text-xs px-1.5 py-0.5 rounded" style="background:rgba(111,255,210,0.1);color:var(--accent-teal)">Default</span>
                <span class="text-xs px-1.5 py-0.5 rounded" :style="model.is_active ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal)' : 'background:var(--surface-overlay);color:var(--text-muted)'">{{ model.is_active ? 'Active' : 'Disabled' }}</span>
                <span class="text-xs px-1.5 py-0.5 rounded" style="background:var(--surface-overlay);color:#8fa6bc">{{ model.purpose }}</span>
              </div>
              <div class="flex justify-end">
                <button @click.stop="toggleModelActive(model)" class="text-xs px-2 py-1 rounded-lg" style="background:var(--surface-overlay);color:var(--text-secondary)">{{ model.is_active ? 'Disable' : 'Enable' }}</button>
              </div>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="showAddModelForm = !showAddModelForm"
              class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all"
              style="background:rgba(86,216,255,0.1);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.25)">
              + Add Model
            </button>
          </div>

          <div v-if="showAddModelForm" class="mt-4 p-4 rounded-xl space-y-3" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs mb-1 block" style="color:var(--text-muted)">Provider</label>
                <select v-model="newModel.provider" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                  <option value="nvidia">NVIDIA</option>
                  <option value="openai">OpenAI</option>
                  <option value="anthropic">Anthropic</option>
                  <option value="gemini">Gemini</option>
                </select>
              </div>
              <div>
                <label class="text-xs mb-1 block" style="color:var(--text-muted)">Purpose</label>
                <select v-model="newModel.purpose" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                  <option value="price_analysis">Price Analysis</option>
                  <option value="trust_score">Trust Score</option>
                  <option value="investment">Investment</option>
                  <option value="fraud">Fraud</option>
                  <option value="listing_extraction">Listing Extraction</option>
                </select>
              </div>
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Display Name</label>
              <input v-model="newModel.name" placeholder="e.g. Llama 3.1 70B (NVIDIA)" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Model ID</label>
              <input v-model="newModel.model_id" placeholder="e.g. meta/llama-3.1-70b-instruct" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            </div>
            <div class="flex gap-2">
              <button @click="submitNewModel" :disabled="!newModel.name || !newModel.model_id || addModelSaving" class="px-4 py-2 rounded-lg text-xs font-bold disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ addModelSaving?'Saving...':'Save Model' }}</button>
              <button @click="showAddModelForm=false" class="px-4 py-2 rounded-lg text-xs" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
            </div>
          </div>
        </div>

        <!-- API Key Status -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-4 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fbbf24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            API Key Status
          </h2>
          <div v-for="p in providers" :key="p.provider" class="flex items-center gap-3 p-3 rounded-xl mb-2"
            :style="p.configured ? 'background:rgba(111,255,210,0.05);border:1px solid rgba(111,255,210,0.15)' : 'background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15)'">
            <div class="w-2 h-2 rounded-full" :style="p.configured ? 'background:#6fffd2' : 'background:#ef4444'"></div>
            <span class="text-sm font-medium capitalize" :style="p.configured ? 'color:var(--accent-teal)' : 'color:#ef4444'">{{ p.provider }} {{ p.configured ? 'Configured' : 'Not Configured' }}</span>
            <span v-if="p.configured" class="ml-auto font-mono text-xs" style="color:var(--text-muted)">{{ p.model }}</span>
          </div>
          <p class="text-xs mt-2" style="color:var(--text-muted)">Keys are stored in the server <code style="color:var(--accent-cyan)">.env</code> and never exposed to the frontend. To rotate, update <code style="color:var(--accent-cyan)">OPENAI_API_KEY</code> / <code style="color:var(--accent-cyan)">NVIDIA_API_KEY</code> in your server environment.</p>
        </div>

        <!-- Test Prompt -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-4 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a78bfa" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Test Connection
          </h2>
          <div class="flex gap-3 mb-4">
            <select v-model="testProvider" class="px-3 py-2.5 rounded-xl text-sm shrink-0" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="openai">OpenAI</option>
              <option value="nvidia">NVIDIA</option>
            </select>
            <input v-model="testPrompt" type="text" placeholder="Type a test message…"
              class="flex-1 px-4 py-2.5 rounded-xl text-sm outline-none"
              style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"
              @keydown.enter="runTest" />
            <button @click="runTest" :disabled="testing"
              class="px-4 py-2.5 rounded-xl text-sm font-bold shrink-0 transition-all"
              style="background:rgba(167,139,250,0.12);color:var(--accent-violet);border:1px solid rgba(167,139,250,0.25)">
              {{ testing ? '…' : 'Test' }}
            </button>
          </div>
          <div v-if="testResult" class="p-4 rounded-xl text-sm leading-relaxed" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle);color:var(--text-secondary);white-space:pre-wrap">{{ testResult }}</div>
        </div>
      </div>

      <!-- Right: Usage & Log -->
      <div class="space-y-5">

        <!-- Usage Stats -->
        <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h3 class="font-bold mb-4 text-sm" style="color:var(--text-primary)">This Month Usage</h3>
          <div class="space-y-3">
            <div v-for="u in usageBreakdown" :key="u.label">
              <div class="flex justify-between text-xs mb-1">
                <span style="color:var(--text-muted)">{{ u.label }}</span>
                <span style="color:var(--text-secondary)">{{ u.value }}</span>
              </div>
              <div style="height:4px;border-radius:2px;background:var(--border-subtle)">
                <div style="height:4px;border-radius:2px;transition:width .5s" :style="'width:'+u.pct+'%;background:'+u.color"></div>
              </div>
            </div>
          </div>
          <div class="mt-4 pt-4 flex justify-between items-center" style="border-top:1px solid var(--surface-overlay)">
            <span class="text-xs" style="color:var(--text-muted)">Est. Cost</span>
            <span class="text-lg font-black" style="color:var(--accent-teal)">$0.84</span>
          </div>
        </div>

        <!-- Analysis Queue -->
        <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm" style="color:var(--text-primary)">Analysis Log</h3>
            <button @click="runBatchAnalysis" :disabled="batching"
              class="text-xs px-3 py-1.5 rounded-lg font-bold transition"
              style="background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)">
              {{ batching ? 'Running…' : 'Run All Pending' }}
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="log in analysisLog" :key="log.id" class="flex items-center gap-2.5 py-2" style="border-bottom:1px solid var(--surface-overlay)">
              <div class="w-1.5 h-1.5 rounded-full shrink-0" :style="'background:'+log.color"></div>
              <div class="flex-1 min-w-0">
                <p class="text-xs truncate" style="color:var(--text-secondary)">{{ log.title }}</p>
                <p class="text-xs" style="color:var(--text-muted)">{{ log.time }}</p>
              </div>
              <span class="text-xs font-bold shrink-0" :style="'color:'+log.color">{{ log.status }}</span>
            </div>
          </div>
        </div>

        <!-- Prompt Template -->
        <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h3 class="font-bold text-sm mb-3" style="color:var(--text-primary)">Prompt Settings</h3>
          <div class="space-y-3">
            <div>
              <label class="text-xs mb-1.5 block" style="color:var(--text-muted)">Temperature</label>
              <div class="flex items-center gap-3">
                <input type="range" min="0" max="1" step="0.1" v-model="temperature" class="flex-1" />
                <span class="text-xs font-bold w-6 text-right" style="color:var(--accent-cyan)">{{ temperature }}</span>
              </div>
            </div>
            <div>
              <label class="text-xs mb-1.5 block" style="color:var(--text-muted)">Max Tokens</label>
              <select v-model="maxTokens" class="w-full px-3 py-2 rounded-lg text-sm"
                style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-secondary)">
                <option value="200">200 (fast)</option>
                <option value="400">400 (balanced)</option>
                <option value="800">800 (detailed)</option>
              </select>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs" style="color:var(--text-muted)">Auto-analyze on publish</span>
              <button @click="autoAnalyze = !autoAnalyze"
                class="w-9 h-5 rounded-full transition-colors relative"
                :style="autoAnalyze ? 'background:#6fffd2' : 'background:var(--border-strong)'">
                <div class="absolute top-0.5 w-4 h-4 rounded-full transition-all" style="background:white"
                  :style="autoAnalyze ? 'left:calc(100% - 18px)' : 'left:2px'"></div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'

const selectedModel = ref('')
const testPrompt = ref('')
const testResult = ref('')
const testing = ref(false)
const testProvider = ref('openai')
const batching = ref(false)
const temperature = ref(0.3)
const maxTokens = ref('200')
const autoAnalyze = ref(true)

const models = ref<any[]>([])
const modelsLoading = ref(true)
const providers = ref<Array<{ provider: string; configured: boolean; model: string }>>([])
const showAddModelForm = ref(false)
const addModelSaving = ref(false)
const newModel = ref({ provider: 'nvidia', purpose: 'price_analysis', name: '', model_id: '' })

async function loadModels() {
  modelsLoading.value = true
  try {
    const r = await client.get('/admin/ai-models')
    models.value = r.data || []
    if (!selectedModel.value && models.value.length) selectedModel.value = models.value[0].id
  } catch { models.value = [] }
  finally { modelsLoading.value = false }
}

async function loadProviders() {
  try {
    const r = await client.get('/admin/llm/providers')
    providers.value = r.data || []
  } catch { providers.value = [] }
}

onMounted(() => { loadModels(); loadProviders() })

async function toggleModelActive(model: any) {
  await client.put(`/admin/ai-models/${model.id}`, { is_active: !model.is_active })
  loadModels()
}

async function submitNewModel() {
  addModelSaving.value = true
  try {
    await client.post('/admin/ai-models', newModel.value)
    showAddModelForm.value = false
    newModel.value = { provider: 'nvidia', purpose: 'price_analysis', name: '', model_id: '' }
    loadModels()
  } catch (e: any) {
    alert(e?.response?.data?.message || 'Failed to add model.')
  } finally {
    addModelSaving.value = false
  }
}

const stats = [
  { label: 'Analyses Today', value: '47', badge: 'Live', rgb: '111,255,210', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { label: 'Total This Month', value: '1,284', badge: 'Jun', rgb: '86,216,255', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { label: 'Tokens Used', value: '2.4M', badge: 'GPT-4o-mini', rgb: '167,139,250', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  { label: 'Avg Response', value: '1.2s', badge: 'p95', rgb: '251,191,36', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
]

const usageBreakdown = [
  { label: 'Price Analysis', value: '1,048 calls', pct: 82, color: '#6fffd2' },
  { label: 'Batch Re-score', value: '203 calls', pct: 16, color: '#56d8ff' },
  { label: 'Test Calls', value: '33 calls', pct: 3, color: '#a78bfa' },
]

const analysisLog = ref([
  { id: 1, title: '2023 BMW 5-Series • Girne', time: '2 min ago', status: 'Done', color: '#6fffd2' },
  { id: 2, title: 'Sea View Apt • Iskele', time: '8 min ago', status: 'Done', color: '#6fffd2' },
  { id: 3, title: '2021 Toyota Corolla • Lefkosa', time: '15 min ago', status: 'Done', color: '#6fffd2' },
  { id: 4, title: 'Villa 4BR • Kyrenia Hills', time: '31 min ago', status: 'Done', color: '#6fffd2' },
  { id: 5, title: '2019 Mercedes C-Class', time: '1 hr ago', status: 'Pending', color: '#fbbf24' },
])

async function runTest() {
  if (!testPrompt.value.trim()) return
  testing.value = true
  testResult.value = ''
  try {
    const { data } = await client.post('/admin/llm/test', {
      prompt: testPrompt.value,
      provider: testProvider.value,
    })
    testResult.value = data.response ?? 'No response'
  } catch (e: any) {
    testResult.value = '⚠ ' + (e?.response?.data?.error || 'Test failed — check API key and backend logs.')
  } finally {
    testing.value = false
  }
}

async function runBatchAnalysis() {
  batching.value = true
  try {
    await client.post('/admin/listings/batch-analyze')
    analysisLog.value.forEach(l => { if (l.status === 'Pending') l.status = 'Done'; l.color = '#6fffd2' })
  } catch {
    // silent
  } finally {
    batching.value = false
  }
}
</script>
