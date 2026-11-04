<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--bg-base)">
    <div class="relative w-full max-w-md">
      <div class="text-center mb-10">
        <RouterLink to="/" class="inline-flex items-center gap-2.5"><BrandLogo :size="40" /></RouterLink>
        <p class="mt-3 text-sm" style="color:var(--text-muted)">Enter your email to receive a reset link</p>
      </div>
      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div v-if="sent" class="text-center py-4 text-sm" style="color:var(--accent-teal)">
          If an account exists for <strong>{{ email }}</strong>, a reset link is on its way. The link expires in 60 minutes.
        </div>
        <form v-else @submit.prevent="handleSubmit" class="space-y-5">
          <div v-if="error" class="px-4 py-3 rounded-xl text-sm" style="background:rgba(var(--danger-rgb),0.1);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">EMAIL ADDRESS</label>
            <input v-model="email" type="email" required placeholder="you@example.com" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-sm disabled:opacity-60" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
            {{ loading ? 'Sending...' : 'Send Reset Link' }}
          </button>
        </form>
        <p class="mt-6 text-center text-sm"><RouterLink to="/login" style="color:var(--accent-teal)">Back to login</RouterLink></p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import BrandLogo from '@/components/BrandLogo.vue'
import { apiErrorMessage } from '@/api/errors'

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const error = ref('')

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    await client.post('/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not send the reset link. Please try again.')
  } finally {
    loading.value = false
  }
}
</script>
