<template>
  <MainLayout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-20 md:py-24">
      <h1 class="text-4xl md:text-5xl font-black mb-3" style="color:var(--text-primary)">Contact <span class="gradient-text">us</span></h1>
      <p class="mb-12 max-w-xl" style="color:var(--text-muted)">Questions about a listing, your account, pricing or partnerships — we usually reply within one working day.</p>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
        <div class="lg:col-span-3 rounded-2xl p-6 md:p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div v-if="sentRef" class="text-center py-10" role="status">
            <p class="text-4xl mb-4">✅</p>
            <p class="text-lg font-bold mb-2" style="color:var(--text-primary)">Message received</p>
            <p class="text-sm mb-1" style="color:var(--text-secondary)">We'll reply to {{ form.email || auth.user?.email }}.</p>
            <p class="text-xs" style="color:var(--text-muted)">Reference: <span class="font-mono">{{ sentRef }}</span></p>
          </div>

          <form v-else @submit.prevent="submit" class="space-y-5" novalidate>
            <div v-if="error" role="alert" class="px-4 py-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="c-name" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">NAME</label>
                <input id="c-name" v-model="form.name" required autocomplete="name" class="field" />
              </div>
              <div>
                <label for="c-email" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">EMAIL</label>
                <input id="c-email" v-model="form.email" type="email" required autocomplete="email" class="field" />
              </div>
            </div>
            <div>
              <label for="c-subject" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">SUBJECT <span class="font-normal">(optional)</span></label>
              <input id="c-subject" v-model="form.subject" maxlength="200" class="field" />
            </div>
            <div>
              <label for="c-message" class="block text-xs font-semibold mb-2" style="color:var(--text-muted)">MESSAGE</label>
              <textarea id="c-message" v-model="form.message" rows="6" required minlength="10" maxlength="5000" class="field"></textarea>
            </div>
            <!-- Honeypot: invisible to people, bots fill it and get rejected. -->
            <input v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] w-px h-px opacity-0" />
            <button type="submit" :disabled="busy" class="w-full sm:w-auto px-8 py-3.5 rounded-xl font-bold text-sm disabled:opacity-60" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
              {{ busy ? 'Sending…' : 'Send message' }}
            </button>
          </form>
        </div>

        <aside class="lg:col-span-2 space-y-4">
          <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <p class="font-bold mb-1" style="color:var(--text-primary)">Already have an account?</p>
            <p class="text-sm mb-4" style="color:var(--text-muted)">Open a ticket from your dashboard to track the conversation.</p>
            <RouterLink :to="auth.isAuthenticated ? '/dashboard/support' : { name: 'login', query: { redirect: '/dashboard/support' } }" class="text-sm font-semibold" style="color:var(--accent-teal)">Go to Help &amp; Support →</RouterLink>
          </div>
          <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <p class="font-bold mb-1" style="color:var(--text-primary)">Quick answers</p>
            <p class="text-sm mb-4" style="color:var(--text-muted)">How AI pricing works, fees, verification and more.</p>
            <RouterLink to="/faq" class="text-sm font-semibold" style="color:var(--accent-teal)">Read the FAQ →</RouterLink>
          </div>
          <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <p class="font-bold mb-1" style="color:var(--text-primary)">Suspicious listing?</p>
            <p class="text-sm" style="color:var(--text-muted)">Use “Report” on the listing page — reports go straight to our fraud team. Never pay a deposit before viewing.</p>
          </div>
        </aside>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { usePageMeta } from '@/composables/usePageMeta'

const auth = useAuthStore()
const form = ref({ name: auth.user?.name ?? '', email: auth.user?.email ?? '', subject: '', message: '', website: '' })
const busy = ref(false)
const error = ref('')
const sentRef = ref('')

usePageMeta(() => ({ title: 'Contact', description: 'Get in touch with the AIPrice.app team about listings, accounts, pricing or partnerships.' }))

async function submit() {
  error.value = ''
  if (!form.value.name.trim() || !form.value.email.includes('@')) { error.value = 'Please enter your name and a valid email.'; return }
  if (form.value.message.trim().length < 10) { error.value = 'Please write a little more so we can help (at least 10 characters).'; return }
  busy.value = true
  try {
    const r = await client.post('/contact', form.value)
    sentRef.value = r.data.reference
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not send your message. Please try again.')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.field { width: 100%; padding: .75rem 1rem; border-radius: .75rem; font-size: .875rem; outline: none; background: var(--surface-overlay); border: 1px solid var(--border-subtle); color: var(--text-primary); }
.field:focus { border-color: var(--accent-teal); }
</style>
