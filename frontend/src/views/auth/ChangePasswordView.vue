<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--bg-base)">
    <div class="relative w-full max-w-md">
      <div class="text-center mb-10">
        <BrandLogo :size="40" />
        <p class="mt-3 text-sm" style="color:var(--text-muted)">Your account was set up with a temporary password. Choose your own to continue.</p>
      </div>
      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div v-if="error" class="px-4 py-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">TEMPORARY PASSWORD</label>
            <input v-model="current" type="password" required autocomplete="current-password" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">NEW PASSWORD</label>
            <input v-model="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">CONFIRM PASSWORD</label>
            <input v-model="confirm" type="password" required autocomplete="new-password" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-sm disabled:opacity-60" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
            {{ loading ? 'Saving...' : 'Set Password' }}
          </button>
        </form>
        <button @click="signOut" class="w-full mt-4 text-xs" style="color:var(--text-muted)">Sign out instead</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import client from '@/api/client'
import BrandLogo from '@/components/BrandLogo.vue'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const current = ref('')
const password = ref('')
const confirm = ref('')
const loading = ref(false)
const error = ref('')

async function handleSubmit() {
  if (password.value !== confirm.value) {
    error.value = 'Passwords do not match.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await client.post('/auth/change-password', {
      current_password: current.value,
      password: password.value,
      password_confirmation: confirm.value,
    })
    await auth.fetchMe()
    router.replace(auth.homeRoute)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not change the password.')
  } finally {
    loading.value = false
  }
}

async function signOut() {
  await auth.logout()
  router.replace('/login')
}
</script>
