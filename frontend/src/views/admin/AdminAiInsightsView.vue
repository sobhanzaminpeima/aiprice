<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <div class="ai-badge mb-2">⬡ AI Intelligence Center</div>
        <h1 class="text-3xl font-black" style="color:var(--text-primary)">AI Market <span class="gradient-text">Insights</span></h1>
      </div>
      <button class="btn-primary px-5 py-2.5 text-sm" :disabled="loading" @click="load">{{ loading ? 'Loading…' : 'Refresh' }}</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
      <div v-for="i in 3" :key="i" class="rounded-2xl p-6 h-40 animate-pulse" style="background:var(--surface-overlay-soft)"></div>
    </div>

    <div v-else-if="error" class="rounded-2xl p-8 text-center mb-6" style="background:var(--bg-surface);border:1px solid rgba(var(--danger-rgb),0.2)">
      <p class="text-sm mb-3" style="color:var(--danger)">{{ error }}</p>
      <button class="btn-primary px-5 py-2 text-sm" @click="load">Retry</button>
    </div>

    <template v-else>
      <!-- Market Trust Index -->
      <div class="rounded-2xl p-6 mb-6" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.05),rgba(var(--accent-cyan-rgb),0.03));border:1px solid rgba(var(--accent-teal-rgb),0.2)">
        <h2 class="font-bold text-lg mb-5 flex items-center gap-2" style="color:var(--text-primary)">
          <div class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">AI</div>
          Market Trust Index™
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <div v-for="mkt in marketTrust" :key="mkt.label" class="rounded-xl p-5" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-semibold" style="color:var(--text-secondary)">{{ mkt.label }}</span>
              <span class="text-xl font-black" :style="{color: mkt.color}">{{ mkt.score ?? '—' }}</span>
            </div>
            <div v-if="mkt.sample_size === 0" class="text-xs" style="color:var(--text-muted)">No active listings yet</div>
            <div v-else class="space-y-2">
              <div v-for="sub in mkt.breakdown" :key="sub.name" class="flex items-center gap-2">
                <span class="text-xs w-20 truncate" style="color:var(--text-muted)">{{ sub.name }}</span>
                <div class="flex-1 h-1.5 rounded-full" style="background:var(--border-subtle)">
                  <div class="h-1.5 rounded-full" :style="`width:${sub.val ?? 0}%;background:${mkt.color}`"></div>
                </div>
                <span class="text-xs w-6 text-right" :style="{color: mkt.color}">{{ sub.val ?? '—' }}</span>
              </div>
            </div>
            <p class="text-xs mt-2" style="color:var(--text-muted)">{{ mkt.sample_size }} active listing(s) scored</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Undervalued Assets -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.15)">
          <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            Undervalued Assets
          </h2>
          <div v-if="!undervalued.length" class="text-xs py-6 text-center" style="color:var(--text-muted)">No scored listings below market average yet.</div>
          <div v-else class="space-y-3">
            <div v-for="a in undervalued" :key="a.id" class="flex items-center gap-3 p-3 rounded-xl" style="background:rgba(var(--accent-teal-rgb),0.04);border:1px solid rgba(var(--accent-teal-rgb),0.1)">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :style="a.type==='vehicle'?'background:rgba(var(--accent-teal-rgb),0.12)':'background:rgba(var(--accent-violet-rgb),0.12)'">
                <svg v-if="a.type==='vehicle'" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/></svg>
                <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="violet" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ a.title }}</p>
                <p class="text-xs" style="color:var(--text-muted)">Listed: {{ a.listed }} · Market avg: {{ a.avg }}</p>
              </div>
              <span class="text-xs font-black px-2 py-1 rounded-lg whitespace-nowrap" style="background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)">{{ a.diff }} below</span>
            </div>
          </div>
        </div>

        <!-- Overpriced Assets -->
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--danger-rgb),0.15)">
          <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="danger" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Overpriced Listings
          </h2>
          <div v-if="!overpriced.length" class="text-xs py-6 text-center" style="color:var(--text-muted)">No scored listings above market average yet.</div>
          <div v-else class="space-y-3">
            <div v-for="a in overpriced" :key="a.id" class="flex items-center gap-3 p-3 rounded-xl" style="background:rgba(var(--danger-rgb),0.04);border:1px solid rgba(var(--danger-rgb),0.1)">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :style="a.type==='vehicle'?'background:rgba(var(--accent-teal-rgb),0.12)':'background:rgba(var(--accent-violet-rgb),0.12)'">
                <svg v-if="a.type==='vehicle'" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/></svg>
                <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="violet" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ a.title }}</p>
                <p class="text-xs" style="color:var(--text-muted)">Listed: {{ a.listed }} · Market avg: {{ a.avg }}</p>
              </div>
              <span class="text-xs font-black px-2 py-1 rounded-lg whitespace-nowrap" style="background:rgba(var(--danger-rgb),0.12);color:var(--danger)">{{ a.diff }} above</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Demand Analysis + Fast Selling -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="cyan" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Demand by Segment
          </h2>
          <p class="text-xs mb-4" style="color:var(--text-muted)">By type + city — the finest segmentation the current category data supports.</p>
          <div v-if="!demand.length" class="text-xs py-6 text-center" style="color:var(--text-muted)">Not enough scored listings per segment yet (need 2+).</div>
          <div v-else class="space-y-4">
            <div v-for="d in demand" :key="d.label">
              <div class="flex justify-between text-xs mb-1.5">
                <span style="color:var(--text-secondary)">{{ d.label }}</span>
                <span class="font-bold" style="color:var(--accent-cyan)">{{ d.score }}/100</span>
              </div>
              <div class="h-2.5 rounded-full" style="background:var(--border-subtle)">
                <div class="h-2.5 rounded-full transition-all" :style="`width:${d.score}%;background:var(--accent-cyan)`"></div>
              </div>
              <p class="text-xs mt-1" style="color:var(--text-muted)">{{ d.sample_size }} listing(s)</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="warning" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Fast Selling Indicators
          </h2>
          <div v-if="!fastSelling.length" class="text-xs py-6 text-center" style="color:var(--text-muted)">No closed (sold/rented) listings yet to measure time-on-market.</div>
          <div v-else class="space-y-3">
            <div v-for="f in fastSelling" :key="f.type" class="p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-xs font-bold" style="color:var(--text-primary)">{{ f.type }}</p>
                  <p class="text-xs" style="color:var(--text-muted)">Avg days on market: {{ f.avg_days }} · {{ f.closed_count }} closed</p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full font-bold" :style="`background:rgba(${f.hot ? 'var(--accent-teal-rgb)' : 'var(--accent-cyan-rgb)'},0.1);color:${f.hot ? 'var(--accent-teal)' : 'var(--accent-cyan)'}`">
                  {{ f.hot ? 'Faster than avg' : 'Slower than avg' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'

interface MarketTrust { label: string; score: number | null; color: string; sample_size: number; breakdown: { name: string; val: number | null }[] }
interface AssetDiff { id: string; type: string; title: string; listed: string; avg: string; diff: string }
interface DemandSegment { label: string; score: number; sample_size: number }
interface FastSelling { type: string; avg_days: number | null; closed_count: number; hot: boolean }

const colorMap: Record<string, string> = { vehicle: 'var(--accent-teal)', property: 'var(--accent-cyan)', rental: 'var(--accent-violet)' }

const loading = ref(true)
const error = ref('')
const marketTrust = ref<MarketTrust[]>([])
const undervalued = ref<AssetDiff[]>([])
const overpriced = ref<AssetDiff[]>([])
const demand = ref<DemandSegment[]>([])
const fastSelling = ref<FastSelling[]>([])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/admin/ai-insights')
    marketTrust.value = (data.market_trust ?? []).map((m: any) => ({ ...m, color: colorMap[m.type] ?? 'var(--accent-teal)' }))
    undervalued.value = data.undervalued ?? []
    overpriced.value = data.overpriced ?? []
    demand.value = data.demand_by_category ?? []
    fastSelling.value = data.fast_selling ?? []
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Could not load AI insights. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
