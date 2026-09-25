<template>
  <div class="p-8 max-w-2xl">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">KYC <span class="gradient-text">Verification</span></h1>
    <p class="text-sm mb-8" style="color:var(--text-muted)">Verify your identity to unlock full platform features</p>

    <div v-if="loading" class="h-40 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>

    <div v-else-if="loadError" class="rounded-2xl p-6 text-sm" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444">
      {{ loadError }}
    </div>

    <div v-else-if="status === 'approved'" class="rounded-2xl p-6" style="background:rgba(111,255,210,0.06);border:1px solid rgba(111,255,210,0.2)">
      <div class="flex items-center gap-3">
        <span class="text-3xl">✅</span>
        <div>
          <p class="font-bold" style="color:var(--accent-teal)">Identity Verified</p>
          <p class="text-sm" style="color:var(--text-secondary)">Your KYC verification is complete. You have full access.</p>
        </div>
      </div>
    </div>

    <div v-else-if="status === 'pending'" class="rounded-2xl p-6" style="background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2)">
      <div class="flex items-center gap-3">
        <span class="text-3xl">⏳</span>
        <div>
          <p class="font-bold" style="color:#fbbf24">Under Review</p>
          <p class="text-sm" style="color:var(--text-secondary)">
            Submitted {{ fmtDate(kyc?.created_at) }}. We will email you when a reviewer has looked at it.
          </p>
        </div>
      </div>
    </div>

    <div v-else class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div v-if="status === 'rejected'" class="flex items-center gap-3 mb-6 p-4 rounded-xl" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2)">
        <span class="text-2xl">⚠️</span>
        <div>
          <p class="font-semibold text-sm" style="color:#ef4444">Previous submission rejected</p>
          <p class="text-xs" style="color:var(--text-secondary)">{{ kyc?.rejection_reason || 'Please re-submit with clearer documents.' }}</p>
        </div>
      </div>
      <div v-else class="flex items-center gap-3 mb-6 p-4 rounded-xl" style="background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2)">
        <span class="text-2xl">📄</span>
        <div>
          <p class="font-semibold text-sm" style="color:#fbbf24">Not Verified Yet</p>
          <p class="text-xs" style="color:var(--text-secondary)">Submit your documents for review</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Document Type</label>
          <select v-model="form.document_type" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
            <option value="passport">Passport</option>
            <option value="national_id">National ID</option>
            <option value="driving_license">Driving License</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Document Number</label>
          <input v-model="form.document_number" placeholder="e.g. A12345678" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Full Name (as on document)</label>
            <input v-model="form.full_name" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Date of Birth</label>
            <input v-model="form.date_of_birth" type="date" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <label v-for="f in fileFields" :key="f.name" class="block p-3 rounded-xl text-center text-xs cursor-pointer truncate" style="background:var(--surface-overlay-soft);border:2px dashed var(--border-strong);color:var(--text-muted)">
            <input type="file" accept="image/*" class="hidden" @change="onFile(f.name, $event)" />
            📎 {{ files[f.name]?.name || f.label }}
          </label>
        </div>

        <div v-if="msg" class="text-xs py-2 px-3 rounded-lg" :style="msg.ok?'background:rgba(111,255,210,0.08);color:var(--accent-teal)':'background:rgba(239,68,68,0.08);color:#ef4444'">{{ msg.text }}</div>

        <button type="submit" :disabled="submitting" class="w-full py-3 font-semibold text-sm rounded-xl disabled:opacity-50" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
          {{ submitting ? 'Submitting...' : 'Submit for Review' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(true)
const loadError = ref('')
const submitting = ref(false)
const msg = ref<{ ok: boolean; text: string } | null>(null)
const status = ref<'not_submitted' | 'pending' | 'approved' | 'rejected'>('not_submitted')
const kyc = ref<any>(null)

const fileFields = [
  { name: 'document_front', label: 'Front' },
  { name: 'document_back', label: 'Back' },
  { name: 'selfie', label: 'Selfie' },
]

const form = ref<Record<string, string>>({ document_type: 'passport', document_number: '', full_name: '', date_of_birth: '' })
const files = ref<Record<string, File>>({})

function onFile(name: string, event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) files.value = { ...files.value, [name]: file }
}

function fmtDate(d?: string) {
  return d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : ''
}

async function load() {
  try {
    const r = await client.get('/kyc')
    kyc.value = r.data?.kyc ?? null
    status.value = r.data?.verified ? 'approved' : (r.data?.status ?? 'not_submitted')
  } catch (e: any) {
    loadError.value = e?.response?.data?.message || 'Could not load your verification status.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  submitting.value = true
  msg.value = null
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => { if (v) fd.append(k, v) })
    Object.entries(files.value).forEach(([k, f]) => fd.append(k, f))

    await client.post('/kyc', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    await auth.fetchMe()
    await load()
    msg.value = { ok: true, text: 'Documents submitted for review!' }
  } catch (e: any) {
    const errors = e?.response?.data?.errors
    msg.value = {
      ok: false,
      text: errors ? Object.values(errors).flat().join(' ') : (e?.response?.data?.message || 'Submission failed.'),
    }
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>
