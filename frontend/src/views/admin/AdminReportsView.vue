<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Reports <span class="gradient-text">Center</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Generate and download platform reports</p>
      </div>
    </div>

    <!-- Quick Generate -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
      <div v-for="r in reportTypes" :key="r.title" class="rounded-2xl p-5 cursor-pointer hover:scale-105 transition-transform" :style="`background:var(--bg-surface);border:1px solid rgba(${r.rgb},0.2)`">
        <div class="text-3xl mb-3">{{ r.icon }}</div>
        <h3 class="font-bold mb-1" style="color:var(--text-primary)">{{ r.title }}</h3>
        <p class="text-xs mb-4" style="color:var(--text-muted)">{{ r.desc }}</p>
        <div class="flex gap-2">
          <button class="flex-1 py-2 rounded-xl text-xs font-semibold" :style="`background:rgba(${r.rgb},0.1);color:rgb(${r.rgb});border:1px solid rgba(${r.rgb},0.2)`">
            📊 CSV
          </button>
          <button class="flex-1 py-2 rounded-xl text-xs font-semibold" :style="`background:rgba(${r.rgb},0.1);color:rgb(${r.rgb});border:1px solid rgba(${r.rgb},0.2)`">
            📄 PDF
          </button>
        </div>
      </div>
    </div>

    <!-- Recent Reports -->
    <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="font-bold mb-5" style="color:var(--text-primary)">Recent Reports</h2>
      <div class="space-y-3">
        <div v-for="rpt in recentReports" :key="rpt.name" class="flex items-center gap-4 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
          <span class="text-xl">{{ rpt.icon }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold" style="color:var(--text-primary)">{{ rpt.name }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ rpt.date }} · {{ rpt.size }}</p>
          </div>
          <span class="text-xs px-2 py-1 rounded-full" :style="`background:rgba(${rpt.rgb},0.1);color:rgb(${rpt.rgb})`">{{ rpt.type }}</span>
          <button class="text-xs px-3 py-1.5 rounded-lg font-medium" style="background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)">Download</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const reportTypes = [
  { icon: '📊', title: 'Listings Report', desc: 'All active, pending, and rejected listings with full details', rgb: '111,255,210' },
  { icon: '👥', title: 'Users Report', desc: 'Registered users, KYC status, subscription details', rgb: '86,216,255' },
  { icon: '💰', title: 'Revenue Report', desc: 'Subscription payments, plan breakdown, MRR trends', rgb: '167,139,250' },
  { icon: '🛡', title: 'Fraud Report', desc: 'Flagged listings, banned users, risk incidents', rgb: '239,68,68' },
  { icon: '🤖', title: 'AI Analysis Report', desc: 'AI scores, trust metrics, overpriced/undervalued summary', rgb: '251,191,36' },
  { icon: '📈', title: 'Market Report', desc: 'Price trends by city and category, competitor comparison', rgb: '111,255,210' },
]
const recentReports = [
  { icon: '📊', name: 'Listings Report — June 2026', date: '2026-06-19', size: '2.4 MB', type: 'CSV', rgb: '111,255,210' },
  { icon: '💰', name: 'Revenue Report — May 2026', date: '2026-06-01', size: '0.8 MB', type: 'PDF', rgb: '167,139,250' },
  { icon: '👥', name: 'User Report — Q1 2026', date: '2026-04-01', size: '1.1 MB', type: 'CSV', rgb: '86,216,255' },
  { icon: '🛡', name: 'Fraud Incidents — Q2 2026', date: '2026-06-01', size: '0.4 MB', type: 'PDF', rgb: '239,68,68' },
]
</script>
