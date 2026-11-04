<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Fraud <span class="gradient-text">Detection</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">User reports plus rule-based risk signals. Every flag shows exactly why it was raised.</p>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-6 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
      <div v-for="s in statCards" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" :style="{ color: s.color }">{{ s.value }}</p>
        <p class="text-xs" style="color:var(--text-muted)">{{ s.label }}</p>
      </div>
    </div>

    <!-- Reports -->
    <div class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">User reports</h2>
        <div class="flex gap-1">
          <button v-for="s in ['open', 'confirmed', 'dismissed', 'all']" :key="s" @click="setFilter(s)" class="px-3 py-1.5 rounded-lg text-xs capitalize" :style="filter === s ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)' : 'color:var(--text-muted)'">{{ s }}</button>
        </div>
      </div>

      <p v-if="!reports.length" class="text-sm py-6 text-center" style="color:var(--text-muted)">No {{ filter === 'all' ? '' : filter }} reports.</p>
      <div v-else class="space-y-3">
        <div v-for="r in reports" :key="r.id" class="p-4 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-2">
            <div class="min-w-0">
              <RouterLink :to="`/listings/${r.listing?.slug}`" target="_blank" class="text-sm font-semibold hover:underline" style="color:var(--accent-cyan)">{{ r.listing?.title || 'Deleted listing' }}</RouterLink>
              <p class="text-xs" style="color:var(--text-muted)">
                Seller {{ r.listing?.user?.name }} ({{ r.listing?.user?.email }}) {{ r.listing?.user?.kyc_verified ? '· KYC ✓' : '· not verified' }}
                · reported by {{ r.reporter?.name }} · {{ fmtDate(r.created_at) }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="r.risk_score !== null" class="text-xs px-2 py-0.5 rounded-full font-bold" :style="riskStyle(r.risk_score)">risk {{ Math.round(r.risk_score) }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full capitalize" :style="statusStyle(r.status)">{{ r.status }}</span>
            </div>
          </div>
          <p class="text-xs mb-1" style="color:var(--text-primary)"><strong>{{ r.reason }}</strong><span v-if="r.description"> — {{ r.description }}</span></p>
          <ul v-if="r.ai_analysis?.signals?.length" class="text-xs mb-3 flex flex-wrap gap-1.5">
            <li v-for="s in r.ai_analysis.signals" :key="s.key" class="px-2 py-0.5 rounded" style="background:rgba(var(--warning-rgb),0.08);color:var(--warning)">{{ s.reason }}</li>
          </ul>
          <div v-if="r.status === 'pending' || r.status === 'investigating'" class="flex flex-wrap gap-2">
            <button v-if="r.status === 'pending'" @click="update(r, 'investigating')" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)">Investigate</button>
            <button @click="confirmFraud(r)" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:rgba(var(--danger-rgb),0.1);color:var(--danger)">Confirm fraud &amp; take down</button>
            <button @click="update(r, 'dismissed')" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:var(--surface-overlay);color:var(--text-muted)">Dismiss</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Flagged listings -->
    <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="font-bold mb-1" style="color:var(--text-primary)">Risk-flagged live listings</h2>
      <p class="text-xs mb-5" style="color:var(--text-muted)">Active listings with at least one risk signal, riskiest first. Nobody reported these — review proactively.</p>
      <p v-if="!flagged.length" class="text-sm py-6 text-center" style="color:var(--text-muted)">No live listing currently shows a risk signal.</p>
      <div v-else class="space-y-2">
        <div v-for="l in flagged" :key="l.id" class="flex flex-wrap items-center gap-3 p-3 rounded-xl" style="background:var(--surface-overlay-soft)">
          <span class="text-xs font-black w-10 text-center py-1 rounded" :style="riskStyle(l.risk.score)">{{ l.risk.score }}</span>
          <div class="flex-1 min-w-0">
            <RouterLink :to="`/listings/${l.slug}`" target="_blank" class="text-sm font-semibold truncate block hover:underline" style="color:var(--text-primary)">{{ l.title }}</RouterLink>
            <p class="text-xs truncate" style="color:var(--text-muted)">{{ l.risk.signals.map((s: any) => s.reason).join(' · ') }}</p>
          </div>
          <RouterLink :to="`/admin/listings/${l.id}/edit`" class="text-xs" style="color:var(--accent-teal)">Review →</RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const error = ref('')
const stats = ref<any>({})
const flagged = ref<any[]>([])
const reports = ref<any[]>([])
const filter = ref('open')

const statCards = computed(() => [
  { label: 'Pending reports', value: stats.value.pending ?? 0, color: 'var(--warning)' },
  { label: 'Investigating', value: stats.value.investigating ?? 0, color: 'var(--accent-cyan)' },
  { label: 'Confirmed (30d)', value: stats.value.confirmed_30d ?? 0, color: 'var(--danger)' },
  { label: 'Dismissed (30d)', value: stats.value.dismissed_30d ?? 0, color: 'var(--text-muted)' },
  { label: 'High-risk live listings', value: stats.value.high_risk_listings ?? 0, color: '#f472b6' },
])

async function loadOverview() {
  const r = await client.get('/admin/fraud/overview')
  stats.value = r.data.stats
  flagged.value = r.data.flagged_listings
}

async function loadReports() {
  const r = await client.get('/admin/fraud/reports', { params: { status: filter.value } })
  reports.value = r.data.data
}

function setFilter(s: string) {
  filter.value = s
  loadReports().catch((e) => (error.value = apiErrorMessage(e)))
}

async function update(r: any, status: string, archive = false) {
  try {
    await client.put(`/admin/fraud/reports/${r.id}`, { status, archive_listing: archive })
    await Promise.all([loadReports(), loadOverview()])
  } catch (e) {
    error.value = apiErrorMessage(e, 'Update failed.')
  }
}

function confirmFraud(r: any) {
  if (window.confirm(`Confirm fraud on “${r.listing?.title}”? The listing is taken down and other open reports on it are closed.`)) {
    update(r, 'confirmed', true)
  }
}

function riskStyle(score: number) {
  if (score >= 50) return 'background:rgba(var(--danger-rgb),0.12);color:var(--danger)'
  if (score >= 25) return 'background:rgba(var(--warning-rgb),0.12);color:var(--warning)'
  return 'background:var(--surface-overlay);color:var(--text-muted)'
}
function statusStyle(s: string) {
  if (s === 'confirmed') return 'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'
  if (s === 'dismissed') return 'background:var(--surface-overlay);color:var(--text-muted)'
  if (s === 'investigating') return 'background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)'
  return 'background:rgba(var(--warning-rgb),0.1);color:var(--warning)'
}
function fmtDate(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }

onMounted(async () => {
  try {
    await Promise.all([loadOverview(), loadReports()])
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load fraud data.')
  }
})
</script>
