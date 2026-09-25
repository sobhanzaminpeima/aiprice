<template>
  <div v-if="show" class="mb-6 px-4 py-3 rounded-xl flex flex-wrap items-center gap-3 text-sm" style="background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.25)">
    <span>✉️</span>
    <p class="flex-1 min-w-[200px]" style="color:var(--text-secondary)">
      Verify <strong style="color:var(--text-primary)">{{ auth.user?.email }}</strong> to publish listings and contact sellers.
    </p>
    <button @click="resend" :disabled="sending || sent" class="px-3 py-1.5 rounded-lg text-xs font-semibold disabled:opacity-60" style="background:rgba(251,191,36,0.12);color:#fbbf24">
      {{ sent ? 'Sent — check your inbox' : sending ? 'Sending…' : 'Resend email' }}
    </button>
    <p v-if="error" class="w-full text-xs" style="color:#ef4444">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const sending = ref(false)
const sent = ref(false)
const error = ref('')

// Older cached sessions may lack the flag; fall back to the raw timestamp.
const show = computed(() => {
  const u = auth.user as any
  if (!u) return false
  return u.email_verified === false || (u.email_verified === undefined && !u.email_verified_at)
})

async function resend() {
  sending.value = true
  error.value = ''
  try {
    await client.post('/auth/email/resend')
    sent.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not send the email.')
  } finally {
    sending.value = false
  }
}
</script>
