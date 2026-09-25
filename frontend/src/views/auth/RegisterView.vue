<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <RouterLink to="/" class="inline-flex items-center gap-2 mb-6">
          <BrandLogo :size="40" />
        </RouterLink>
        <h1 class="text-3xl font-black text-white mb-2">Create Account</h1>
        <p class="text-slate-400">Join the AI marketplace revolution</p>
      </div>
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8">
        <div v-if="error" class="mb-4 p-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg text-sm">{{ error }}</div>
        <form @submit.prevent="handleRegister" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Full Name</label>
            <input v-model="form.name" type="text" required class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm outline-none focus:border-sky-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Email</label>
            <input v-model="form.email" type="email" required class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm outline-none focus:border-sky-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">I am a...</label>
            <select v-model="form.role" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm outline-none focus:border-sky-500 transition">
              <option value="buyer">Buyer</option>
              <option value="seller">Seller</option>
              <option value="dealer">Dealer</option>
              <option value="agency">Real Estate Agency</option>
              <option value="investor">Investor</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
            <input v-model="form.password" type="password" required minlength="8" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm outline-none focus:border-sky-500 transition" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" required class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-sm outline-none focus:border-sky-500 transition" />
          </div>
          <button type="submit" :disabled="loading" class="w-full py-3 bg-gradient-to-r from-sky-500 to-violet-600 text-white font-bold rounded-xl hover:opacity-90 transition disabled:opacity-50">
            {{ loading ? 'Creating...' : 'Create Free Account' }}
          </button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-400">
          Already have an account? <RouterLink to="/login" class="text-sky-400 hover:text-sky-300 font-medium">Sign in</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { apiErrorMessage } from '@/api/errors'
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BrandLogo from '@/components/BrandLogo.vue'
const auth = useAuthStore(); const router = useRouter()
const form = ref({ name: '', email: '', password: '', password_confirmation: '', role: 'buyer' })
const loading = ref(false); const error = ref('')
async function handleRegister() {
  loading.value = true; error.value = ''
  try { await auth.register(form.value); router.push('/dashboard') }
  catch (e: any) { error.value = apiErrorMessage(e, 'Registration failed') }
  finally { loading.value = false }
}
</script>