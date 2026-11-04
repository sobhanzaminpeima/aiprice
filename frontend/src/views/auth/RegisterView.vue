<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-12" style="background:var(--bg-base)">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="absolute top-[-20%] left-[30%] w-[600px] h-[600px] rounded-full" style="background:radial-gradient(circle,rgba(var(--accent-teal-rgb),0.05) 0%,transparent 70%)"></div>
    </div>
    <div class="relative w-full max-w-md">
      <div class="text-center mb-8">
        <RouterLink to="/" class="inline-flex items-center gap-2.5"><BrandLogo :size="40" /></RouterLink>
        <h1 class="mt-4 text-2xl font-black" style="color:var(--text-primary)">{{ $t('Create your account') }}</h1>
        <p class="mt-1 text-sm" style="color:var(--text-muted)">{{ $t('Free forever. Know the real market price before you buy or sell.') }}</p>
      </div>

      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <form @submit.prevent="handleRegister" class="space-y-4" novalidate>
          <div v-if="error" role="alert" class="px-4 py-3 rounded-xl text-sm" style="background:rgba(var(--danger-rgb),0.1);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

          <fieldset>
            <legend class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">{{ $t('I WANT TO') }}</legend>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="r in roles" :key="r.value" type="button" @click="form.role = r.value" :aria-pressed="form.role === r.value"
                class="px-3 py-2.5 rounded-xl text-xs font-semibold text-left"
                :style="form.role === r.value ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.3)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">
                {{ r.label }}
              </button>
            </div>
          </fieldset>

          <div>
            <label for="reg-name" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">{{ $t('FULL NAME') }}</label>
            <input id="reg-name" v-model="form.name" required autocomplete="name" class="field" />
          </div>
          <div>
            <label for="reg-email" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">{{ $t('EMAIL ADDRESS') }}</label>
            <input id="reg-email" v-model="form.email" type="email" required autocomplete="email" placeholder="you@example.com" class="field" />
          </div>
          <div>
            <label for="reg-pass" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">{{ $t('PASSWORD') }}</label>
            <input id="reg-pass" v-model="form.password" type="password" required minlength="8" autocomplete="new-password" aria-describedby="reg-pass-hint" class="field" />
            <p id="reg-pass-hint" class="text-[11px] mt-1.5" :style="{ color: form.password && form.password.length < 8 ? 'var(--warning)' : 'var(--text-muted)' }">{{ $t('At least 8 characters') }}</p>
          </div>
          <div>
            <label for="reg-pass2" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">{{ $t('CONFIRM PASSWORD') }}</label>
            <input id="reg-pass2" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="field" :aria-invalid="mismatch" />
            <p v-if="mismatch" class="text-[11px] mt-1.5" style="color:var(--warning)">{{ $t('Passwords don\'t match yet') }}</p>
          </div>

          <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-sm disabled:opacity-60" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
            {{ loading ? $t('Creating account…') : $t('Create free account') }}
          </button>
          <p class="text-[11px] text-center" style="color:var(--text-muted)">{{ $t('We\'ll email you a link to verify your address.') }}</p>
          <p class="text-[11px] text-center" style="color:var(--text-muted)">
            {{ $t('By creating an account you agree to the') }}
            <RouterLink to="/terms" class="underline">{{ $t('Terms') }}</RouterLink> ·
            <RouterLink to="/privacy" class="underline">{{ $t('Privacy') }}</RouterLink>
          </p>
        </form>

        <p class="text-center text-sm mt-6" style="color:var(--text-muted)">
          {{ $t('Already have an account?') }}
          <RouterLink :to="{ name: 'login', query: route.query.redirect ? { redirect: route.query.redirect } : {} }" class="font-semibold ml-1" style="color:var(--accent-teal)">{{ $t('Sign in') }}</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BrandLogo from '@/components/BrandLogo.vue'
import { apiErrorMessage } from '@/api/errors'
import { usePageMeta } from '@/composables/usePageMeta'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const roles = [
  { value: 'buyer', label: 'Buy a car or home' },
  { value: 'seller', label: 'Sell privately' },
  { value: 'dealer', label: 'Sell as a dealer' },
  { value: 'agency', label: 'List as an agency' },
  { value: 'investor', label: 'Invest' },
]

const form = ref({ name: '', email: '', password: '', password_confirmation: '', role: 'buyer' })
const loading = ref(false)
const error = ref('')
const mismatch = computed(() => !!form.value.password_confirmation && form.value.password !== form.value.password_confirmation)

usePageMeta(() => ({ title: 'Create account', description: 'Join AIPrice.app free and see an AI price estimate for cars and property in North Cyprus.' }))

async function handleRegister() {
  error.value = ''
  if (!form.value.name.trim() || !form.value.email.trim()) { error.value = 'Please fill in your name and email.'; return }
  if (form.value.password.length < 8) { error.value = 'Password must be at least 8 characters.'; return }
  if (mismatch.value) { error.value = 'Passwords do not match.'; return }

  loading.value = true
  try {
    await auth.register(form.value)
    // Only follow in-app redirects, never an absolute URL from the query string.
    const redirect = String(route.query.redirect ?? '')
    router.push(redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/dashboard')
  } catch (e) {
    error.value = apiErrorMessage(e, 'Registration failed')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.field { width: 100%; padding: .75rem 1rem; border-radius: .75rem; font-size: .875rem; outline: none; background: var(--surface-overlay); border: 1px solid var(--border-subtle); color: var(--text-primary); }
.field:focus { border-color: var(--accent-teal); }
</style>
