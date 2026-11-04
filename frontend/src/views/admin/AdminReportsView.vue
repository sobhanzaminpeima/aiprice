<template>
  <div class="p-8 max-w-4xl">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Data <span class="gradient-text">Exports</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">CSV exports open in Excel and Google Sheets. Exports contain personal data — handle them accordingly.</p>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-4 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <div class="rounded-2xl p-5 mb-6 flex flex-wrap items-end gap-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div>
        <label class="block text-xs mb-1" style="color:var(--text-muted)">From</label>
        <input v-model="from" type="date" class="px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
      </div>
      <div>
        <label class="block text-xs mb-1" style="color:var(--text-muted)">To</label>
        <input v-model="to" type="date" class="px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
      </div>
      <p class="text-xs pb-2" style="color:var(--text-muted)">Leave empty to export everything.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="r in reports" :key="r.key" class="rounded-2xl p-5" :style="`background:var(--bg-surface);border:1px solid var(--border-subtle);${r.allowed ? '' : 'opacity:.5'}`">
        <p class="font-bold mb-1" style="color:var(--text-primary)">{{ r.label }}</p>
        <p class="text-xs mb-4" style="color:var(--text-muted)">{{ r.description }}</p>
        <button v-if="r.allowed" @click="download(r.key)" :disabled="busy === r.key" class="px-4 py-2 rounded-xl text-xs font-bold disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
          {{ busy === r.key ? 'Preparing…' : 'Download CSV' }}
        </button>
        <p v-else class="text-xs" style="color:var(--text-muted)">Your role cannot export this data.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const reports = ref<any[]>([])
const from = ref('')
const to = ref('')
const busy = ref<string | null>(null)
const error = ref('')

// The export needs the bearer token, so fetch it as a blob rather than
// pointing the browser at the URL directly.
async function download(key: string) {
  busy.value = key
  error.value = ''
  try {
    const r = await client.get(`/admin/reports/${key}/export`, {
      params: { from: from.value || undefined, to: to.value || undefined },
      responseType: 'blob',
    })
    const name = /filename="?([^"]+)"?/.exec(r.headers['content-disposition'] ?? '')?.[1] ?? `aiprice-${key}.csv`
    const url = URL.createObjectURL(r.data)
    const a = Object.assign(document.createElement('a'), { href: url, download: name })
    a.click()
    URL.revokeObjectURL(url)
  } catch (e: any) {
    // Error bodies arrive as a Blob when responseType is blob.
    if (e?.response?.data instanceof Blob) {
      try { e.response.data = JSON.parse(await e.response.data.text()) } catch { /* keep generic */ }
    }
    error.value = apiErrorMessage(e, 'Export failed.')
  } finally {
    busy.value = null
  }
}

onMounted(async () => {
  try {
    reports.value = (await client.get('/admin/reports')).data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load exports.')
  }
})
</script>
