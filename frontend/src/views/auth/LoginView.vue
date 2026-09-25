<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--bg-base)">
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
      <div class="absolute top-[-20%] left-[30%] w-[600px] h-[600px] rounded-full" style="background:radial-gradient(circle,rgba(111,255,210,0.05) 0%,transparent 70%)"></div>
    </div>
    <div class="relative w-full max-w-md">
      <div class="text-center mb-10">
        <RouterLink to="/" class="inline-flex items-center gap-2.5">
          <BrandLogo :size="40" />
        </RouterLink>
        <p class="mt-3 text-sm" style="color:var(--text-muted)">Sign in to your account</p>
      </div>
      <div class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <form @submit.prevent="handleLogin" class="space-y-5">
          <div v-if="error" class="px-4 py-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>
          <div>
            <label class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">EMAIL ADDRESS</label>
            <input v-model="form.email" type="email" required placeholder="you@example.com" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-semibold" style="color:var(--text-muted)">PASSWORD</label>
              <RouterLink to="/forgot-password" class="text-xs" style="color:var(--accent-teal)">Forgot?</RouterLink>
            </div>
            <input v-model="form.password" type="password" required placeholder="••••••••" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          </div>
          <button type="submit" :disabled="loading" class="w-full py-3.5 rounded-xl font-bold text-sm disabled:opacity-60" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
            {{ loading ? "Signing in..." : "Sign In" }}
          </button>
        </form>
        <p class="text-center text-sm mt-6" style="color:var(--text-muted)">
          No account?
          <RouterLink to="/register" class="font-semibold ml-1" style="color:var(--accent-teal)">Create one free</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from "vue"
import { RouterLink, useRouter, useRoute } from "vue-router"
import { useAuthStore } from "@/stores/auth"
import BrandLogo from "@/components/BrandLogo.vue"
import { apiErrorMessage } from "@/api/errors"
const auth = useAuthStore(); const router = useRouter(); const route = useRoute()
const form = ref({ email: "", password: "" }); const loading = ref(false); const error = ref("")
async function handleLogin() {
  loading.value = true; error.value = ""
  try {
    await auth.login(form.value.email, form.value.password)
    const redirect = route.query.redirect as string
    if (auth.isAdmin) router.push(redirect || "/admin")
    else router.push(redirect || "/dashboard")
  } catch (e: any) {
    error.value = apiErrorMessage(e, "Invalid credentials.")
  } finally { loading.value = false }
}
</script>