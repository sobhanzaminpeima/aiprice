<template>
  <div class="p-4 md:p-8 max-w-4xl">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)"><span class="gradient-text">{{ $t('My subscription') }}</span></h1>
    <p class="text-sm mb-6" style="color:var(--text-muted)">{{ $t('Your plan, limits and invoices') }}</p>

    <div v-if="route.query.started === 'trial' && data?.is_trial" class="rounded-2xl p-4 mb-6 text-sm font-semibold" style="background:rgba(var(--accent-teal-rgb),0.08);border:1px solid rgba(var(--accent-teal-rgb),0.25);color:var(--accent-teal)">
      {{ $t('Your free trial has started. Enjoy every feature of {plan} until {date}.', { plan: $t(sub?.plan?.name ?? ''), date: fmtDate(sub?.current_period_end) }) }}
    </div>

    <div v-if="loading" class="h-40 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>

    <div v-else-if="error" role="alert" class="rounded-2xl p-6 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <template v-else-if="data">
      <!-- Awaiting payment -->
      <div v-if="data.pending" class="rounded-2xl p-6 mb-6" style="background:rgba(var(--warning-rgb),0.06);border:1px solid rgba(var(--warning-rgb),0.3)">
        <p class="text-xs font-bold uppercase mb-1" style="color:var(--warning)">{{ $t('Awaiting payment') }}</p>
        <p class="font-bold mb-2" style="color:var(--text-primary)">
          {{ $t(data.pending.plan?.name ?? '') }} · {{ data.pending.currency }} {{ Number(data.pending.amount).toFixed(2) }}/{{ data.pending.billing_cycle === 'yearly' ? $t('yr') : $t('mo') }}
        </p>
        <p class="text-sm mb-3" style="color:var(--text-muted)">{{ $t('Your plan is activated as soon as we receive your payment.') }}</p>
        <div class="rounded-xl p-4 text-sm whitespace-pre-line mb-3" style="background:var(--bg-surface);color:var(--text-secondary)">{{ data.payment_instructions }}</div>
        <button @click="withdraw" :disabled="busy" class="text-xs font-medium disabled:opacity-50" style="color:var(--danger)">{{ $t('Withdraw request') }}</button>
      </div>

      <!-- Current plan -->
      <div class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.15)">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <p class="text-xs font-medium mb-1" style="color:var(--text-muted)">{{ $t('Current plan') }}</p>
            <p class="text-2xl font-black" style="color:var(--accent-teal)">{{ $t(data.is_active ? (sub?.plan?.name ?? '') : (data.usage.plan_name ?? 'Private')) }}</p>
          </div>
          <span class="text-xs px-3 py-1.5 rounded-full font-medium whitespace-nowrap" :style="data.is_active ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)' : 'background:var(--surface-overlay);color:var(--text-muted)'">
            {{ data.is_trial ? $t('Free trial') : data.is_active ? $t('Active') : $t('Free plan') }}
          </span>
        </div>

        <div v-if="data.is_active" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xs mb-1" style="color:var(--text-muted)">{{ data.is_trial ? $t('Trial ends') : sub?.cancelled_at ? $t('Access ends') : $t('Paid until') }}</p>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ fmtDate(sub?.current_period_end) }} <span v-if="daysLeft !== null" style="color:var(--text-muted)">· {{ $t('{n} days left', { n: daysLeft }) }}</span></p>
          </div>
          <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
            <p class="text-xs mb-1" style="color:var(--text-muted)">{{ $t('Price') }}</p>
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ sub?.currency || 'GBP' }} {{ Number(sub?.amount ?? 0).toFixed(2) }}/{{ sub?.billing_cycle === 'yearly' ? $t('yr') : $t('mo') }}</p>
          </div>
        </div>
        <p v-else-if="sub" class="text-xs mb-4" style="color:var(--text-muted)">{{ $t('Your {plan} plan ended on {date}.', { plan: $t(sub.plan?.name ?? ''), date: fmtDate(sub.current_period_end) }) }}</p>

        <div class="space-y-3">
          <UsageBar :label="$t('Active listings')" :used="data.usage.listings" :limit="data.usage.listing_limit" />
          <UsageBar v-if="data.usage.featured_limit" :label="$t('Featured listings')" :used="data.usage.featured" :limit="data.usage.featured_limit" />
        </div>
        <p class="text-xs mt-3" style="color:var(--text-muted)">
          {{ $t('{n} photos per listing · listings stay up {d} days', { n: data.usage.photos_per_listing, d: data.usage.listing_duration_days }) }}
        </p>

        <div v-if="data.is_trial" class="mt-5 flex flex-wrap gap-3">
          <button @click="payNow" :disabled="busy" class="px-4 py-2.5 rounded-xl text-sm font-bold disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('Keep this plan after the trial') }}</button>
        </div>
        <p v-if="data.is_active && sub?.cancelled_at" class="mt-4 text-xs" style="color:var(--warning)">{{ $t('Cancelled — you keep access until {date}.', { date: fmtDate(sub?.current_period_end) }) }}</p>
        <button v-else-if="data.is_active && !data.is_trial && sub?.payment_gateway !== 'manual'" @click="cancel" :disabled="busy" class="mt-4 text-xs font-medium disabled:opacity-50" style="color:var(--danger)">{{ $t('Cancel subscription') }}</button>
        <p v-if="notice" class="mt-4 text-sm" style="color:var(--accent-teal)">{{ notice }}</p>
      </div>

      <RouterLink :to="{ path: '/pricing', query: { audience: planAudience } }" class="flex items-center justify-between p-4 rounded-2xl font-semibold mb-8" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.08),rgba(var(--accent-cyan-rgb),0.08));border:1px solid rgba(var(--accent-teal-rgb),0.2);color:var(--accent-teal)">
        <span>{{ data.is_active ? $t('Change plan') : data.trial_eligible ? $t('Try a dealer or agency plan free for a month') : $t('Upgrade plan') }}</span><span aria-hidden="true">→</span>
      </RouterLink>

      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">{{ $t('Invoices') }}</h2>
        <p v-if="!invoices.length" class="text-sm" style="color:var(--text-muted)">{{ $t('No invoices yet.') }}</p>
        <div v-else class="space-y-2">
          <RouterLink v-for="inv in invoices" :key="inv.id" :to="`/dashboard/invoices/${inv.id}`" class="flex flex-wrap items-center gap-3 p-3 rounded-xl hover:opacity-80" style="background:var(--surface-overlay-soft)">
            <span class="text-xs font-mono flex-1" style="color:var(--text-secondary)">{{ inv.invoice_number }}</span>
            <span class="text-xs" style="color:var(--text-muted)">{{ fmtDate(inv.created_at) }}</span>
            <span class="text-xs font-bold text-right" style="color:var(--text-primary)">{{ inv.currency }} {{ Number(inv.total).toFixed(2) }}</span>
            <span class="text-[10px] uppercase font-bold text-right" :style="inv.status === 'paid' ? 'color:var(--accent-teal)' : 'color:var(--danger)'">{{ inv.status === 'void' ? $t('refunded') : $t(inv.status) }}</span>
          </RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { t, locale } from '@/i18n'

const UsageBar = defineComponent({
  props: { label: { type: String, required: true }, used: { type: Number, required: true }, limit: { type: Number, default: null } },
  setup(props) {
    return () => {
      const pct = props.limit ? Math.min(100, Math.round((props.used / props.limit) * 100)) : 0
      return h('div', { class: 'p-3 rounded-xl', style: 'background:var(--surface-overlay-soft)' }, [
        h('div', { class: 'flex items-center justify-between mb-2' }, [
          h('p', { class: 'text-xs', style: 'color:var(--text-muted)' }, props.label),
          h('p', { class: 'text-xs font-semibold', style: 'color:var(--text-primary)' }, props.limit ? `${props.used} / ${props.limit}` : String(props.used)),
        ]),
        props.limit ? h('div', { class: 'h-1.5 rounded-full overflow-hidden', style: 'background:var(--surface-overlay)' }, [
          h('div', { class: 'h-full rounded-full', style: { width: pct + '%', background: pct >= 100 ? 'var(--danger)' : 'linear-gradient(90deg,var(--accent-teal),var(--accent-cyan))' } }),
        ]) : null,
      ])
    }
  },
})

const route = useRoute()
const data = ref<any>(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const invoices = ref<any[]>([])

const sub = computed(() => data.value?.subscription ?? null)
const planAudience = computed(() => sub.value?.plan?.type ?? 'dealer')
const daysLeft = computed(() => {
  const end = sub.value?.current_period_end
  if (!data.value?.is_active || !end) return null
  return Math.max(0, Math.ceil((new Date(end).getTime() - Date.now()) / 86400000))
})

async function load() {
  try {
    data.value = (await client.get('/my-subscription')).data
  } catch (e) {
    error.value = apiErrorMessage(e, t('Could not load your subscription.'))
  } finally {
    loading.value = false
  }
}

async function withdraw() {
  if (!data.value?.pending || !window.confirm(t('Withdraw this plan request?'))) return
  busy.value = true
  try { await client.post(`/my-subscription/${data.value.pending.id}/cancel`); await load() }
  catch (e) { error.value = apiErrorMessage(e) }
  finally { busy.value = false }
}

/** Ask to continue the trial plan as a paid plan once the trial ends. */
async function payNow() {
  if (!sub.value) return
  busy.value = true
  notice.value = ''
  try {
    const r = await client.post('/billing/subscribe', { plan_id: sub.value.plan_id, cycle: 'monthly', pay_now: true })
    if (r.data.mode === 'checkout') { window.location.href = r.data.url; return }
    notice.value = t('Thanks! Payment details are shown above. Your paid period starts when your trial ends.')
    await load()
  } catch (e) { error.value = apiErrorMessage(e) }
  finally { busy.value = false }
}

async function cancel() {
  if (!sub.value || !window.confirm(t('Cancel your subscription? You keep access until the end of the current period.'))) return
  busy.value = true
  try { await client.post(`/my-subscription/${sub.value.id}/cancel`); await load() }
  catch (e) { error.value = apiErrorMessage(e, t('Could not cancel the subscription.')) }
  finally { busy.value = false }
}

function fmtDate(d?: string) {
  const loc = locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB'
  return d ? new Date(d).toLocaleDateString(loc, { day: 'numeric', month: 'long', year: 'numeric' }) : '—'
}

onMounted(() => {
  load()
  client.get('/billing/invoices').then((r) => (invoices.value = r.data.data)).catch(() => {})
})
</script>
