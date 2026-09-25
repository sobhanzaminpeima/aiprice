<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Financial <span class="gradient-text">Overview</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Revenue, subscriptions & transactions</p>
      </div>
      <button class="btn-outline px-5 py-2.5 text-sm">Export Report</button>
    </div>

    <!-- Revenue stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="s in stats" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-xs mb-3" style="color:var(--text-muted)">{{ s.label }}</p>
        <p class="text-2xl font-black" :style="{color: s.color}">{{ s.value }}</p>
        <p class="text-xs mt-1" :style="s.up ? 'color:var(--accent-teal)' : 'color:#ef4444'">{{ s.up ? '↑' : '↓' }} {{ s.change }} vs last month</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- Monthly Revenue Chart (bar representation) -->
      <div class="lg:col-span-2 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Monthly Revenue (Last 6 Months)</h2>
        <div class="flex items-end gap-3 h-36">
          <div v-for="m in monthlyRevenue" :key="m.month" class="flex-1 flex flex-col items-center gap-2">
            <span class="text-xs font-bold" :style="{color: m.current ? '#6fffd2' : 'var(--text-muted)'}">£{{ m.val }}</span>
            <div class="w-full rounded-t-lg transition-all" :style="`height:${m.pct}%;background:${m.current ? 'linear-gradient(to top,#6fffd2,#56d8ff)' : 'var(--border-subtle)'};min-height:8px`"></div>
            <span class="text-xs" style="color:var(--text-muted)">{{ m.month }}</span>
          </div>
        </div>
      </div>

      <!-- Plan Breakdown -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Plan Breakdown</h2>
        <div class="space-y-3">
          <div v-for="plan in planBreakdown" :key="plan.name" class="flex items-center gap-3">
            <div class="w-3 h-3 rounded-sm shrink-0" :style="{background: plan.color}"></div>
            <div class="flex-1">
              <div class="flex justify-between text-xs mb-1">
                <span style="color:var(--text-secondary)">{{ plan.name }}</span>
                <span style="color:var(--text-primary)" class="font-bold">{{ plan.count }}</span>
              </div>
              <div class="h-1.5 rounded-full" style="background:var(--border-subtle)">
                <div class="h-1.5 rounded-full" :style="`width:${plan.pct}%;background:${plan.color}`"></div>
              </div>
            </div>
            <span class="text-xs font-bold shrink-0" :style="{color: plan.color}">{{ plan.pct }}%</span>
          </div>
        </div>
        <div class="mt-5 pt-4" style="border-top:1px solid var(--border-subtle)">
          <p class="text-xs" style="color:var(--text-muted)">Total active subscriptions</p>
          <p class="text-2xl font-black mt-1" style="color:var(--text-primary)">{{ planBreakdown.reduce((s,p) => s+p.count, 0) }}</p>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="font-bold mb-5" style="color:var(--text-primary)">Recent Transactions</h2>
      <table class="w-full">
        <thead>
          <tr class="text-xs" style="color:var(--text-muted);border-bottom:1px solid var(--border-subtle)">
            <th class="text-left pb-3 font-medium">User</th>
            <th class="text-left pb-3 font-medium">Plan</th>
            <th class="text-left pb-3 font-medium">Amount</th>
            <th class="text-left pb-3 font-medium">Date</th>
            <th class="text-left pb-3 font-medium">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tx in transactions" :key="tx.id" class="text-xs border-b" style="border-color:var(--surface-overlay)">
            <td class="py-3" style="color:var(--text-primary)">{{ tx.user }}</td>
            <td class="py-3">
              <span class="px-2 py-0.5 rounded-full font-medium" :style="`background:rgba(${tx.planColor},0.1);color:rgb(${tx.planColor})`">{{ tx.plan }}</span>
            </td>
            <td class="py-3 font-bold" style="color:var(--accent-teal)">{{ tx.amount }}</td>
            <td class="py-3" style="color:var(--text-muted)">{{ tx.date }}</td>
            <td class="py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :style="tx.status === 'paid' ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal)' : 'background:rgba(251,191,36,0.1);color:#fbbf24'">
                {{ tx.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
const stats = [
  { label: 'Monthly Revenue', value: '£2,840', color: '#6fffd2', up: true, change: '18%' },
  { label: 'Annual Run Rate', value: '£34,080', color: '#56d8ff', up: true, change: '23%' },
  { label: 'Active Subscribers', value: '47', color: '#a78bfa', up: true, change: '12%' },
  { label: 'Avg Revenue / User', value: '£60', color: '#fbbf24', up: false, change: '4%' },
]
const monthlyRevenue = [
  { month: 'Jan', val: '1.2k', pct: 42 },
  { month: 'Feb', val: '1.5k', pct: 53 },
  { month: 'Mar', val: '1.8k', pct: 64 },
  { month: 'Apr', val: '2.1k', pct: 74 },
  { month: 'May', val: '2.5k', pct: 88 },
  { month: 'Jun', val: '2.8k', pct: 100, current: true },
]
const planBreakdown = [
  { name: 'Free', count: 89, pct: 65, color: 'var(--text-muted)' },
  { name: 'Premium Seller', count: 28, pct: 20, color: '#6fffd2' },
  { name: 'Dealer Plan', count: 14, pct: 10, color: '#56d8ff' },
  { name: 'Agency', count: 5, pct: 4, color: '#a78bfa' },
  { name: 'Enterprise', count: 1, pct: 1, color: '#fbbf24' },
]
const transactions = [
  { id: 1, user: 'Ahmad Motors', plan: 'Dealer', amount: '£99/mo', date: '2026-06-19', status: 'paid', planColor: '86,216,255' },
  { id: 2, user: 'Kyrenia Realty', plan: 'Agency', amount: '£149/mo', date: '2026-06-18', status: 'paid', planColor: '167,139,250' },
  { id: 3, user: 'private.user@cy', plan: 'Premium', amount: '£29/mo', date: '2026-06-17', status: 'paid', planColor: '111,255,210' },
  { id: 4, user: 'bestcars.nc', plan: 'Dealer', amount: '£99/mo', date: '2026-06-16', status: 'pending', planColor: '86,216,255' },
  { id: 5, user: 'villa.invest.cy', plan: 'Premium', amount: '£29/mo', date: '2026-06-15', status: 'paid', planColor: '111,255,210' },
]
</script>
