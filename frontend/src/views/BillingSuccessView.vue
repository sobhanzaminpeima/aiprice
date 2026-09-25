<template>
  <div class="min-h-screen flex items-center justify-center px-4" style="background:var(--bg-base)">
    <div class="w-full max-w-md text-center rounded-2xl p-10" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <template v-if="state === 'waiting'">
        <div class="w-10 h-10 mx-auto mb-5 rounded-full border-2 animate-spin" style="border-color:var(--accent-teal);border-top-color:transparent"></div>
        <p class="font-bold mb-1" style="color:var(--text-primary)">Confirming your payment…</p>
        <p class="text-sm" style="color:var(--text-muted)">This usually takes a few seconds.</p>
      </template>

      <template v-else-if="state === 'active'">
        <p class="text-4xl mb-4">🎉</p>
        <p class="text-xl font-black mb-2" style="color:var(--text-primary)">You're on {{ planName }}</p>
        <p class="text-sm mb-8" style="color:var(--text-secondary)">A receipt is on its way to {{ auth.user?.email }}.</p>
        <RouterLink to="/dashboard/subscription" class="inline-block px-6 py-3 rounded-xl font-bold text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Go to my subscription</RouterLink>
      </template>

      <template v-else>
        <p class="text-4xl mb-4">⏳</p>
        <p class="font-bold mb-2" style="color:var(--text-primary)">Payment received — activation is taking longer than usual</p>
        <p class="text-sm mb-8" style="color:var(--text-secondary)">You don't need to pay again. Your plan will switch on automatically; if it hasn't within a few minutes, contact support.</p>
        <div class="flex gap-3 justify-center">
          <RouterLink to="/dashboard/subscription" class="px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Check subscription</RouterLink>
          <RouterLink to="/dashboard/support" class="px-5 py-2.5 rounded-xl text-sm" style="color:var(--text-secondary);border:1px solid var(--border-subtle)">Contact support</RouterLink>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const state = ref<'waiting' | 'active' | 'slow'>('waiting')
const planName = ref('')
let timer: number | undefined
let attempts = 0

// The plan is switched on by the provider's webhook, which can land a few
// seconds after the redirect back here — poll instead of trusting the URL.
async function check() {
  attempts++
  try {
    const r = await client.get('/my-subscription')
    const sub = r.data?.subscription
    const fresh = sub && r.data.is_active && Date.now() - new Date(sub.created_at).getTime() < 30 * 60 * 1000
    if (fresh) {
      planName.value = sub.plan?.name ?? 'your new plan'
      state.value = 'active'
      auth.fetchMe().catch(() => {})
      return
    }
  } catch { /* keep polling */ }

  if (attempts >= 20) state.value = 'slow'
  else timer = window.setTimeout(check, 2000)
}

onMounted(check)
onBeforeUnmount(() => window.clearTimeout(timer))
</script>
