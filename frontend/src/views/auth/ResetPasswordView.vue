<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--bg-base)">
    <div class="relative w-full max-w-md">
      <div class="text-center mb-10">
        <RouterLink to="/" class="inline-flex items-center gap-2.5"><BrandLogo :size="40" /></RouterLink>
        <p class="mt-3 text-sm" style="color:var(--text-muted)">Choose a new password</p>
      </div>
      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div v-if="!token || !email" class="text-sm text-center py-4" style="color:var(--danger)">
          This reset link is incomplete. Please request a new one.
          <p class="mt-4"><RouterLink to="/forgot-password" style="color:var(--accent-teal)">Request a new link</RouterLink></p>
        </div>

        <div v-else-if="done" class="text-center py-4">
          <p class="text-sm mb-6" style="color:var(--accent-teal)">Your password has been reset. All other sessions were signed out.</p>
          <RouterLink to="/login" class="inline-block px-6 py-3 rounded-xl font-bold text-sm" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Sign in</RouterLink>
        </div>

        <form v-else @submit.prevent="handleSubmit" class="space-y-5">
          <div v-if="error" class="px-4 py-3 rounded-xl text-sm" style="background:rgba(var(--danger-rgb),0.1);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">EMAIL</label>
            <input :value="email" disabled class="w-full px-4 py-3 rounded-xl text-sm opacity-70" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">NEW PASSWORD</label>
            <input v-model="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">CONFIRM PASSWORD</label>
            <input v-model="confirm" type="password" required autocomplete="new-password" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-sm disabled:opacity-60" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
            {{ loading ? 'Saving...' : 'Reset Password' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import client from '@/api/client'
import BrandLogo from '@/components/BrandLogo.vue'
import { apiErrorMessage } from '@/api/errors'

const route = useRoute()
const token = String(route.query.token ?? '')
const email = String(route.query.email ?? '')

const password = ref('')
const confirm = ref('')
const loading = ref(false)
const done = ref(false)
const error = ref('')

async function handleSubmit() {
  if (password.value !== confirm.value) {
    error.value = 'Passwords do not match.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await client.post('/auth/reset-password', {
      token,
      email,
      password: password.value,
      password_confirmation: confirm.value,
    })
    done.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, 'This reset link is invalid or has expired.')
  } finally {
    loading.value = false
  }
}
</script>
