<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">KYC <span class="gradient-text">Review</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Identity documents submitted by users. Documents are private — they load only for reviewers.</p>
    </div>

    <div class="flex gap-2 mb-6">
      <button v-for="s in ['pending', 'approved', 'rejected', 'all']" :key="s" @click="setFilter(s)"
        class="px-4 py-2 rounded-xl text-xs font-semibold capitalize transition-all"
        :style="statusFilter === s ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)' : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid transparent'">{{ s }}</button>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-4 text-sm" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>

    <div v-if="loading" class="space-y-3"><div v-for="i in 3" :key="i" class="h-40 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div></div>

    <div v-else-if="items.length === 0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p class="text-4xl mb-3">🪪</p>
      <p class="font-semibold" style="color:var(--text-primary)">No {{ statusFilter === 'all' ? '' : statusFilter }} submissions</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="k in items" :key="k.id" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ k.user?.name }}</p>
            <p class="text-xs" style="color:var(--text-secondary)">{{ k.user?.email }} · {{ k.user?.role }}</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">Submitted {{ fmtDate(k.created_at) }}</p>
          </div>
          <span class="text-xs px-3 py-1 rounded-full capitalize" :style="statusStyle(k.status)">{{ k.status }}</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-xs">
          <div><p style="color:var(--text-muted)">Document</p><p class="font-semibold capitalize" style="color:var(--text-primary)">{{ String(k.document_type).replace('_', ' ') }}</p></div>
          <div><p style="color:var(--text-muted)">Number</p><p class="font-semibold" style="color:var(--text-primary)">{{ k.document_number || '—' }}</p></div>
          <div><p style="color:var(--text-muted)">Name on document</p><p class="font-semibold" style="color:var(--text-primary)">{{ k.full_name || '—' }}</p></div>
          <div><p style="color:var(--text-muted)">Date of birth</p><p class="font-semibold" style="color:var(--text-primary)">{{ k.date_of_birth ? fmtDate(k.date_of_birth) : '—' }}</p></div>
        </div>

        <div class="flex gap-3 mb-4">
          <template v-for="f in docFields" :key="f.key">
            <button v-if="k[f.key]" @click="openDoc(k, f.key)" class="px-3 py-2 rounded-lg text-xs font-medium" style="background:var(--surface-overlay);color:var(--accent-cyan);border:1px solid var(--border-subtle)">
              {{ docUrls[k.id + f.key] ? 'Opened ' : 'View ' }}{{ f.label }}
            </button>
          </template>
          <span v-if="!docFields.some((f) => k[f.key])" class="text-xs" style="color:var(--text-muted)">No document images uploaded</span>
        </div>

        <div v-if="k.status === 'rejected' && k.rejection_reason" class="text-xs mb-4 p-3 rounded-lg" style="background:rgba(239,68,68,0.06);color:#ef4444">Reason: {{ k.rejection_reason }}</div>

        <div v-if="k.status === 'pending'" class="flex flex-wrap items-center gap-3">
          <button @click="approve(k)" :disabled="busy === k.id" class="px-4 py-2 rounded-xl text-xs font-bold disabled:opacity-50" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Approve</button>
          <input v-model="reasons[k.id]" placeholder="Rejection reason (shown to the user)" class="flex-1 min-w-[220px] px-3 py-2 rounded-xl text-xs outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <button @click="reject(k)" :disabled="busy === k.id || !reasons[k.id]" class="px-4 py-2 rounded-xl text-xs font-bold disabled:opacity-40" style="background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2)">Reject</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const items = ref<any[]>([])
const loading = ref(true)
const error = ref('')
const statusFilter = ref('pending')
const busy = ref<string | null>(null)
const reasons = ref<Record<string, string>>({})
const docUrls = ref<Record<string, string>>({})

const docFields = [
  { key: 'document_front', label: 'front' },
  { key: 'document_back', label: 'back' },
  { key: 'selfie', label: 'selfie' },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    const r = await client.get('/admin/kyc', { params: { status: statusFilter.value } })
    items.value = r.data?.data ?? []
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load KYC submissions.')
  } finally {
    loading.value = false
  }
}

function setFilter(s: string) {
  statusFilter.value = s
  load()
}

// The document endpoint needs the bearer token, which an <img src> can't
// send — fetch the file through the API client and open it as a blob URL.
async function openDoc(k: any, field: string) {
  const cacheKey = k.id + field
  try {
    if (!docUrls.value[cacheKey]) {
      const r = await client.get(`/kyc/${k.id}/${field}`, { responseType: 'blob' })
      docUrls.value = { ...docUrls.value, [cacheKey]: URL.createObjectURL(r.data) }
    }
    window.open(docUrls.value[cacheKey], '_blank', 'noopener')
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not open the document.')
  }
}

async function approve(k: any) {
  busy.value = k.id
  try {
    await client.post(`/admin/users/${k.user_id}/approve-kyc`)
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Approval failed.')
  } finally {
    busy.value = null
  }
}

async function reject(k: any) {
  busy.value = k.id
  try {
    await client.post(`/admin/users/${k.user_id}/reject-kyc`, { reason: reasons.value[k.id] })
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Rejection failed.')
  } finally {
    busy.value = null
  }
}

function statusStyle(s: string) {
  if (s === 'approved') return 'background:rgba(111,255,210,0.1);color:var(--accent-teal)'
  if (s === 'rejected') return 'background:rgba(239,68,68,0.1);color:#ef4444'
  return 'background:rgba(251,191,36,0.1);color:#fbbf24'
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(load)
onBeforeUnmount(() => Object.values(docUrls.value).forEach((u) => URL.revokeObjectURL(u)))
</script>
