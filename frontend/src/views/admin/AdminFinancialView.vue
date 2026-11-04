<template>
  <div class="p-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Financial <span class="gradient-text">Overview</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Revenue, subscriptions &amp; transactions</p>
      </div>
      <RouterLink v-if="auth.can('view_analytics')" to="/admin/reports" class="btn-outline px-5 py-2.5 text-sm">Export payments</RouterLink>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-6 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <div v-if="loading" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="i in 4" :key="i" class="h-28 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>

    <template v-else-if="data">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div v-for="s in statCards" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <p class="text-xs mb-3" style="color:var(--text-muted)">{{ s.label }}</p>
          <p class="text-2xl font-black" :style="{ color: s.color }">{{ s.value }}</p>
          <p v-if="s.change !== undefined" class="text-xs mt-1" :style="changeStyle(s.change)">{{ changeText(s.change) }}</p>
          <p v-else-if="s.note" class="text-xs mt-1" style="color:var(--text-muted)">{{ s.note }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-5" style="color:var(--text-primary)">Monthly Revenue (last 6 months)</h2>
          <p v-if="maxMonth === 0" class="text-sm py-12 text-center" style="color:var(--text-muted)">No completed payments in this period yet.</p>
          <div v-else class="flex items-end gap-3 h-40">
            <div v-for="(m, i) in data.monthly_revenue" :key="m.key" class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
              <span class="text-xs font-bold" :style="{ color: i === data.monthly_revenue.length - 1 ? 'var(--accent-teal)' : 'var(--text-muted)' }">{{ money(m.amount, true) }}</span>
              <div class="w-full rounded-t-lg" :style="`height:${Math.max(4, (m.amount / maxMonth) * 100)}%;background:${i === data.monthly_revenue.length - 1 ? 'linear-gradient(to top,var(--accent-teal),var(--accent-cyan))' : 'var(--border-subtle)'}`"></div>
              <span class="text-xs" style="color:var(--text-muted)">{{ m.month }}</span>
            </div>
          </div>
        </div>

        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-5" style="color:var(--text-primary)">Active plans</h2>
          <p v-if="!data.plan_breakdown.length" class="text-sm" style="color:var(--text-muted)">No active subscriptions.</p>
          <div v-else class="space-y-3">
            <div v-for="(plan, i) in data.plan_breakdown" :key="plan.name" class="flex items-center gap-3">
              <div class="w-3 h-3 rounded-sm shrink-0" :style="{ background: palette[Number(i) % palette.length] }"></div>
              <div class="flex-1">
                <div class="flex justify-between text-xs mb-1">
                  <span style="color:var(--text-secondary)">{{ plan.name }}</span>
                  <span class="font-bold" style="color:var(--text-primary)">{{ plan.count }}</span>
                </div>
                <div class="h-1.5 rounded-full" style="background:var(--border-subtle)">
                  <div class="h-1.5 rounded-full" :style="`width:${pct(plan.count)}%;background:${palette[Number(i) % palette.length]}`"></div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-5 pt-4" style="border-top:1px solid var(--border-subtle)">
            <p class="text-xs" style="color:var(--text-muted)">Total active subscriptions</p>
            <p class="text-2xl font-black mt-1" style="color:var(--text-primary)">{{ data.stats.active_subscriptions }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <h2 class="font-bold" style="color:var(--text-primary)">Transactions</h2>
          <div class="flex gap-2">
            <input v-model="search" @keyup.enter="loadPayments(1)" placeholder="Search email or number" class="px-3 py-2 rounded-lg text-xs outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
            <select v-model="statusFilter" @change="loadPayments(1)" class="px-3 py-2 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)">
              <option value="">All statuses</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>
        </div>
        <p v-if="!payments.length" class="text-sm py-8 text-center" style="color:var(--text-muted)">No transactions{{ statusFilter || search ? ' match these filters' : ' yet' }}.</p>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-xs" style="color:var(--text-muted);border-bottom:1px solid var(--border-subtle)">
                <th class="text-left pb-3 font-medium">Number</th>
                <th class="text-left pb-3 font-medium">User</th>
                <th class="text-left pb-3 font-medium">Plan</th>
                <th class="text-left pb-3 font-medium">Amount</th>
                <th class="text-left pb-3 font-medium">Gateway</th>
                <th class="text-left pb-3 font-medium">Date</th>
                <th class="text-left pb-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in payments" :key="p.id" class="text-xs border-b" style="border-color:var(--surface-overlay)">
                <td class="py-3 font-mono" style="color:var(--text-secondary)">{{ p.payment_number }}</td>
                <td class="py-3" style="color:var(--text-primary)">{{ p.user?.name }}<br /><span style="color:var(--text-muted)">{{ p.user?.email }}</span></td>
                <td class="py-3" style="color:var(--text-secondary)">{{ p.subscription?.plan?.name || '—' }}</td>
                <td class="py-3 font-bold" style="color:var(--accent-teal)">{{ money(p.amount, false, p.currency) }}</td>
                <td class="py-3 capitalize" style="color:var(--text-secondary)">{{ p.gateway || '—' }}</td>
                <td class="py-3" style="color:var(--text-muted)">{{ fmtDate(p.created_at) }}</td>
                <td class="py-3"><span class="px-2 py-0.5 rounded-full font-medium capitalize" :style="statusStyle(p.status)">{{ p.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
          <button :disabled="page <= 1" @click="loadPayments(page - 1)" class="px-3 py-1.5 rounded-lg text-xs disabled:opacity-40" style="background:var(--surface-overlay);color:var(--text-secondary)">Prev</button>
          <span class="text-xs py-1.5" style="color:var(--text-muted)">{{ page }} / {{ lastPage }}</span>
          <button :disabled="page >= lastPage" @click="loadPayments(page + 1)" class="px-3 py-1.5 rounded-lg text-xs disabled:opacity-40" style="background:var(--surface-overlay);color:var(--text-secondary)">Next</button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(true)
const error = ref('')
const data = ref<any>(null)
const payments = ref<any[]>([])
const page = ref(1)
const lastPage = ref(1)
const statusFilter = ref('')
const search = ref('')
const palette = ['var(--accent-teal)', 'var(--accent-cyan)', 'var(--accent-violet)', 'var(--warning)', '#f472b6']

const maxMonth = computed(() => Math.max(0, ...(data.value?.monthly_revenue ?? []).map((m: any) => m.amount)))
const totalPlans = computed(() => (data.value?.plan_breakdown ?? []).reduce((s: number, p: any) => s + p.count, 0))

const statCards = computed(() => {
  const s = data.value?.stats ?? {}
  return [
    { label: 'Revenue this month', value: money(s.revenue_this_month), color: 'var(--accent-teal)', change: s.revenue_change_pct },
    { label: 'MRR', value: money(s.mrr), color: 'var(--accent-cyan)', note: `${s.active_subscriptions ?? 0} active subscriptions` },
    { label: 'New subscriptions', value: String(s.new_subscriptions ?? 0), color: 'var(--accent-violet)', change: s.new_subscriptions_change_pct },
    { label: 'Churn this month', value: `${s.churn_rate_pct ?? 0}%`, color: 'var(--warning)', note: `${s.failed_payments_this_month ?? 0} failed payments` },
  ]
})

function money(v: number, compact = false, currency = 'GBP') {
  const n = Number(v ?? 0)
  const digits = compact || n >= 1000 ? 0 : 2
  // min must be set with max: currencies default to min 2, and max < min throws a RangeError.
  return new Intl.NumberFormat('en-GB', {
    style: 'currency', currency, minimumFractionDigits: digits, maximumFractionDigits: digits,
    notation: compact && n >= 10000 ? 'compact' : 'standard',
  }).format(n)
}
function pct(count: number) { return totalPlans.value ? Math.round((count / totalPlans.value) * 100) : 0 }
function changeText(c: number | null) {
  if (c === null) return 'new — no prior month'
  if (c === 0) return 'no change vs last month'
  return `${c > 0 ? '↑' : '↓'} ${Math.abs(c)}% vs last month`
}
function changeStyle(c: number | null) {
  if (c === null || c === 0) return 'color:var(--text-muted)'
  return c > 0 ? 'color:var(--accent-teal)' : 'color:var(--danger)'
}
function statusStyle(s: string) {
  if (s === 'completed') return 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)'
  if (s === 'failed') return 'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'
  if (s === 'refunded') return 'background:rgba(var(--accent-violet-rgb),0.1);color:var(--accent-violet)'
  return 'background:rgba(var(--warning-rgb),0.1);color:var(--warning)'
}
function fmtDate(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }

async function loadPayments(p = 1) {
  try {
    const r = await client.get('/admin/financial/payments', { params: { page: p, status: statusFilter.value || undefined, search: search.value || undefined } })
    payments.value = r.data.data
    page.value = r.data.current_page
    lastPage.value = r.data.last_page
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load transactions.')
  }
}

onMounted(async () => {
  try {
    const r = await client.get('/admin/financial/overview')
    data.value = r.data
    await loadPayments()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load financial data.')
  } finally {
    loading.value = false
  }
})
</script>
