<template>
  <div>
    <!-- Seller summary: first name + masked phone. The full number is never in
         the public payload; it is fetched from an authenticated endpoint. -->
    <div class="seller-box">
      <div class="seller-avatar" aria-hidden="true"><AppIcon name="user" :size="20" /></div>
      <div class="min-w-0">
        <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ contact?.display_name || $t('Private seller') }}</p>
        <p class="text-xs" style="color:var(--text-muted)">
          {{ $t(ownerLabel) }}<span v-if="contact?.member_since"> · {{ $t('on AIPrice.app since {year}', { year: contact.member_since }) }}</span>
        </p>
      </div>
    </div>

    <div v-if="contact?.has_phone" class="phone-row" :aria-label="revealedPhone ? $t('Seller phone number') : $t('Seller phone number, partly hidden')">
      <AppIcon name="phone" :size="18" />
      <span class="phone-number" :class="{ 'phone-masked': !revealedPhone }" dir="ltr">{{ revealedPhone || contact.phone_masked }}</span>
      <a v-if="revealedPhone" :href="`tel:${revealedPhone.replace(/[^\d+]/g, '')}`" class="phone-action">{{ $t('Call') }}</a>
      <a v-if="revealedPhone && whatsapp" :href="`https://wa.me/${whatsapp}`" target="_blank" rel="noopener noreferrer" class="phone-action">{{ $t('WhatsApp') }}</a>
      <button v-else-if="!revealedPhone" type="button" class="phone-action" :disabled="revealing" @click="revealPhone">
        {{ auth.isAuthenticated ? (revealing ? $t('Loading…') : $t('Show number')) : $t('Sign in to see') }}
      </button>
    </div>
    <p v-if="contact?.has_phone && !auth.isAuthenticated" class="text-xs mt-2" style="color:var(--text-muted)">
      {{ $t('The full number is shown to signed-in members only. Creating an account is free.') }}
    </p>
    <p v-if="phoneError" class="text-xs mt-2" role="alert" style="color:var(--danger)">{{ phoneError }}</p>

    <div class="space-y-3 mt-4">
      <button type="button" class="btn-cta w-full" :aria-expanded="showForm" @click="openForm">
        <AppIcon name="send" :size="18" /> {{ $t(sendLabel) }}
      </button>
      <slot name="secondary" />
    </div>

    <Transition name="slide-down">
      <form v-if="showForm" class="mt-4 pt-4" style="border-top:1px solid var(--border-subtle)" @submit.prevent="send">
        <label :for="`msg-${listingId}`" class="block text-xs font-semibold mb-2" style="color:var(--text-secondary)">{{ $t(isProperty ? 'Your message to the owner' : 'Your message to the seller') }}</label>
        <textarea :id="`msg-${listingId}`" v-model="message" rows="4" maxlength="2000" required minlength="5"
          :placeholder="$t(placeholder)" class="w-full rounded-xl p-3 text-sm resize-none outline-none input-field"></textarea>
        <label class="flex items-start gap-2 mt-3 text-xs cursor-pointer" style="color:var(--text-secondary)">
          <input v-model="shareContact" type="checkbox" class="mt-0.5" />
          <span>{{ $t(isProperty ? 'Share my name, email and phone with the owner so they can reply directly.' : 'Share my name, email and phone with the seller so they can reply directly.') }}</span>
        </label>
        <button type="submit" class="btn-cta w-full mt-3" :disabled="sending">
          {{ sending ? $t('Sending…') : $t(sendLabel) }}
        </button>
        <p v-if="sent" class="text-xs text-center mt-2" role="status" style="color:var(--success)">{{ $t('Message sent. You will see the reply in Dashboard → Messages.') }}</p>
        <p v-if="sendError" class="text-xs text-center mt-2" role="alert" style="color:var(--danger)">{{ sendError }}</p>
      </form>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'

export interface SellerContact {
  display_name: string
  member_since?: string | null
  has_phone: boolean
  phone_masked: string | null
  can_message: boolean
}

const props = defineProps<{ listingId: string; contact: SellerContact | null; kind: 'vehicle' | 'property' | 'rental' | string }>()

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const isProperty = computed(() => props.kind === 'property' || props.kind === 'rental')
const ownerLabel = computed(() => (isProperty.value ? 'Property owner / agent' : 'Seller'))
const sendLabel = computed(() => (isProperty.value ? 'Send to owner' : 'Send to seller'))
const placeholder = computed(() => isProperty.value
  ? 'Hello, is this property still available? When could I arrange a viewing?'
  : 'Hello, is this car still available? Can I see it this week?')

const revealedPhone = ref<string | null>(null)
const whatsapp = ref<string | null>(null)
const revealing = ref(false)
const phoneError = ref('')

const showForm = ref(false)
const message = ref('')
const shareContact = ref(true)
const sending = ref(false)
const sent = ref(false)
const sendError = ref('')

function goToLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}

async function revealPhone() {
  if (!auth.isAuthenticated) return goToLogin()
  revealing.value = true
  phoneError.value = ''
  try {
    const { data } = await client.get(`/listings/${props.listingId}/seller-phone`)
    revealedPhone.value = data.phone
    whatsapp.value = data.whatsapp || null
  } catch (e) {
    phoneError.value = apiErrorMessage(e, 'Could not load the phone number.')
  } finally {
    revealing.value = false
  }
}

function openForm() {
  if (!auth.isAuthenticated) return goToLogin()
  showForm.value = !showForm.value
}

async function send() {
  if (message.value.trim().length < 5) { sendError.value = 'Please write at least a few words.'; return }
  sending.value = true
  sendError.value = ''
  try {
    await client.post(`/listings/${props.listingId}/inquire`, { message: message.value.trim(), share_contact: shareContact.value })
    sent.value = true
    message.value = ''
  } catch (e) {
    sendError.value = apiErrorMessage(e, 'Could not send your message.')
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.seller-box { display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:var(--surface-overlay-soft);border:1px solid var(--border-subtle) }
.seller-avatar { width:40px;height:40px;border-radius:999px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(var(--accent-cyan-rgb),0.12);color:var(--accent-cyan) }
.phone-row { display:flex;align-items:center;gap:10px;margin-top:12px;padding:12px;border-radius:12px;border:1px solid var(--border-strong);color:var(--text-primary);flex-wrap:wrap }
.phone-number { font-size:16px;font-weight:700;letter-spacing:.02em;font-variant-numeric:tabular-nums;flex:1;min-width:140px }
.phone-masked { color:var(--text-secondary) }
.phone-action { font-size:13px;font-weight:700;padding:6px 12px;border-radius:999px;border:1px solid var(--border-cyan);color:var(--accent-cyan);background:rgba(var(--accent-cyan-rgb),0.08);text-decoration:none;cursor:pointer }
.phone-action:disabled { opacity:.6;cursor:wait }
.btn-cta { display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;border-radius:12px;font-weight:700;font-size:14px;border:none;cursor:pointer;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-teal));color:var(--on-accent) }
.btn-cta:disabled { opacity:.6;cursor:wait }
.input-field { background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary) }
.input-field:focus { border-color:var(--accent-cyan) }
</style>
