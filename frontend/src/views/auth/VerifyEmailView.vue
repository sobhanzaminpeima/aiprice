<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--bg-base)">
    <div class="relative w-full max-w-md text-center">
      <RouterLink to="/" class="inline-flex items-center gap-2.5 mb-10"><BrandLogo :size="40" /></RouterLink>
      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <template v-if="state === 'working'">
          <p class="text-sm" style="color:var(--text-muted)">Verifying your email…</p>
        </template>
        <template v-else-if="state === 'done'">
          <p class="text-3xl mb-3">✅</p>
          <p class="font-bold mb-2" style="color:var(--accent-teal)">Email verified</p>
          <p class="text-sm mb-6" style="color:var(--text-secondary)">You can now publish listings and contact sellers.</p>
          <RouterLink :to="auth.isAuthenticated ? auth.homeRoute : '/login'" class="inline-block px-6 py-3 rounded-xl font-bold text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Continue</RouterLink>
        </template>
        <template v-else>
          <p class="text-3xl mb-3">⚠️</p>
          <p class="font-bold mb-2" style="color:#ef4444">Link invalid or expired</p>
          <p class="text-sm mb-6" style="color:var(--text-secondary)">{{ error }}</p>
          <button v-if="auth.isAuthenticated" @click="resend" :disabled="resending" class="px-6 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
            {{ resent ? 'Sent — check your inbox' : resending ? 'Sending…' : 'Send a new link' }}
          </button>
          <RouterLink v-else to="/login" class="text-sm" style="color:var(--accent-teal)">Sign in to request a new link</RouterLink>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import client from '@/api/client'
import BrandLogo from '@/components/BrandLogo.vue'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
const state = ref<'working' | 'done' | 'failed'>('working')
const error = ref('')
const resending = ref(false)
const resent = ref(false)

onMounted(async () => {
  const { id, hash, expires, signature } = route.query as Record<string, string>
  if (!id || !hash || !expires || !signature) {
    state.value = 'failed'
    error.value = 'This link is incomplete.'
    return
  }
  try {
    await client.get(`/auth/email/verify/${id}/${hash}`, { params: { expires, signature } })
    state.value = 'done'
    if (auth.isAuthenticated) await auth.fetchMe()
  } catch (e) {
    state.value = 'failed'
    error.value = apiErrorMessage(e, 'This verification link is invalid or has expired.')
  }
})

async function resend() {
  resending.value = true
  try {
    await client.post('/auth/email/resend')
    resent.value = true
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    resending.value = false
  }
}
</script>
