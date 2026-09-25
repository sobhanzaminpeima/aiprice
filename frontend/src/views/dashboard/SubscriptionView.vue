<template>
  <div class="p-8">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">My <span class="gradient-text">Subscription</span></h1>
    <p class="text-sm mb-8" style="color:var(--text-muted)">Manage your plan and billing</p>

    <div v-if="loading" class="h-40 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>

    <div v-else-if="error" class="rounded-2xl p-6 text-sm" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444">
      {{ error }}
    </div>

    <template v-else>
      <div class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid rgba(111,255,210,0.15)">
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-xs font-medium mb-1" style="color:var(--text-muted)">Current Plan</p>
            <p class="text-2xl font-black" style="color:var(--accent-teal)">{{ sub?.plan?.name || 'Free' }}</p>
          </div>
          <span class="text-xs px-3 py-1.5 rounded-full font-medium" :style="isActive?'background:rgba(111,255,210,0.1);color:var(--accent-teal)':'background:var(--surface-overlay);color:var(--text-muted)'">
            {{ sub?.status || 'No plan' }}
          </span>
        </div>

        <div v-if="sub" class="grid grid-cols-2 gap-4 mt-4">
          <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xs mb-1" style="color:var(--text-muted)">{{ !isActive ? 'Ended' : sub.cancelled_at ? 'Access ends' : 'Renews' }}</p>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ fmtDate(sub.current_period_end) }}</p>
          </div>
          <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xs mb-1" style="color:var(--text-muted)">Price</p>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ sub.currency || 'GBP' }} {{ sub.amount ?? sub.plan?.price_monthly }}/{{ sub.billing_cycle === 'yearly' ? 'yr' : 'mo' }}</p>
          </div>
        </div>

        <div class="p-3 rounded-xl mt-4" style="background:var(--surface-overlay-soft)">
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs" style="color:var(--text-muted)">Listings used</p>
            <p class="text-xs font-semibold" style="color:var(--text-primary)">
              {{ usage.listings }}<span v-if="usage.listing_limit"> / {{ usage.listing_limit }}</span>
            </p>
          </div>
          <div v-if="usage.listing_limit" class="h-1.5 rounded-full overflow-hidden" style="background:var(--surface-overlay)">
            <div class="h-full rounded-full" :style="{ width: usagePercent + '%', background: usagePercent >= 100 ? '#ef4444' : 'linear-gradient(90deg,#6fffd2,#56d8ff)' }"></div>
          </div>
        </div>

        <p v-if="isActive && sub?.cancelled_at" class="mt-4 text-xs" style="color:#fbbf24">Cancelled — you keep access until {{ fmtDate(sub.current_period_end) }}.</p>
        <button v-else-if="isActive" @click="cancel" :disabled="cancelling" class="mt-4 text-xs font-medium disabled:opacity-50" style="color:#ef4444">
          {{ cancelling ? 'Cancelling…' : 'Cancel subscription' }}
        </button>
      </div>

      <RouterLink to="/pricing" class="flex items-center justify-between p-4 rounded-2xl font-semibold mb-8" style="background:linear-gradient(135deg,rgba(111,255,210,0.08),rgba(86,216,255,0.08));border:1px solid rgba(111,255,210,0.2);color:var(--accent-teal)">
        <span>{{ isActive ? 'Change Plan' : 'Upgrade Plan' }}</span><span>→</span>
      </RouterLink>

      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">Invoices</h2>
        <p v-if="!invoices.length" class="text-sm" style="color:var(--text-muted)">No invoices yet.</p>
        <div v-else class="space-y-2">
          <RouterLink v-for="inv in invoices" :key="inv.id" :to="`/dashboard/invoices/${inv.id}`" class="flex items-center gap-3 p-3 rounded-xl hover:opacity-80" style="background:var(--surface-overlay-soft)">
            <span class="text-xs font-mono flex-1" style="color:var(--text-secondary)">{{ inv.invoice_number }}</span>
            <span class="text-xs" style="color:var(--text-muted)">{{ fmtDate(inv.created_at) }}</span>
            <span class="text-xs font-bold w-24 text-right" style="color:var(--text-primary)">{{ inv.currency }} {{ Number(inv.total).toFixed(2) }}</span>
            <span class="text-[10px] uppercase font-bold w-16 text-right" :style="inv.status === 'paid' ? 'color:var(--accent-teal)' : 'color:#ef4444'">{{ inv.status === 'void' ? 'refunded' : inv.status }}</span>
          </RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'

const sub = ref<any>(null)
const usage = ref<{ listings: number; listing_limit: number | null }>({ listings: 0, listing_limit: null })
const isActive = ref(false)
const loading = ref(true)
const cancelling = ref(false)
const error = ref('')
const invoices = ref<any[]>([])

const usagePercent = computed(() =>
  usage.value.listing_limit ? Math.min(100, Math.round((usage.value.listings / usage.value.listing_limit) * 100)) : 0
)

async function load() {
  try {
    const r = await client.get('/my-subscription')
    sub.value = r.data?.subscription ?? null
    usage.value = r.data?.usage ?? usage.value
    isActive.value = !!r.data?.is_active
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Could not load your subscription.'
  } finally {
    loading.value = false
  }
}

async function cancel() {
  if (!sub.value || !window.confirm('Cancel your subscription? You keep access until the end of the current period.')) return
  cancelling.value = true
  try {
    await client.post(`/my-subscription/${sub.value.id}/cancel`)
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Could not cancel the subscription.'
  } finally {
    cancelling.value = false
  }
}

function fmtDate(d?: string) {
  return d ? new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) : 'Never'
}

onMounted(() => {
  load()
  client.get('/billing/invoices').then((r) => (invoices.value = r.data.data)).catch(() => {})
})
</script>
