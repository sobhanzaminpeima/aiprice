<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Fraud <span class="gradient-text">Detection</span></h1>
        <p class="text-sm flex items-center gap-2" style="color:var(--text-muted)">
          <span class="w-2 h-2 rounded-full" style="background:#6fffd2;animation:pulse-glow 1.5s infinite"></span>
          AI monitoring active — real-time threat detection
        </p>
      </div>
      <div class="text-right p-4 rounded-2xl" style="background:rgba(111,255,210,0.06);border:1px solid rgba(111,255,210,0.2)">
        <p class="text-2xl font-black" style="color:var(--accent-teal)">98.4%</p>
        <p class="text-xs" style="color:var(--text-muted)">Fraud Blocked Rate</p>
      </div>
    </div>

    <!-- Risk stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="s in stats" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-xs mb-2" style="color:var(--text-muted)">{{ s.label }}</p>
        <p class="text-2xl font-black" :style="{color: s.color}">{{ s.value }}</p>
        <p class="text-xs mt-1" :style="{color: s.trend > 0 ? '#ef4444' : '#6fffd2'}">{{ s.trend > 0 ? '↑' : '↓' }} {{ Math.abs(s.trend) }} this week</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Active Risk Alerts -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(239,68,68,0.2)">
        <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
          <span style="color:#ef4444">⚠️</span> Active Risk Alerts ({{ alerts.filter(a=>!a.resolved).length }})
        </h2>
        <div class="space-y-3 max-h-80 overflow-y-auto">
          <div v-for="a in alerts" :key="a.id" class="p-4 rounded-xl" :style="a.resolved ? 'background:rgba(255,255,255,0.01);border:1px solid var(--surface-overlay)' : `background:rgba(239,68,68,0.04);border:1px solid rgba(${a.riskColor},0.2)`">
            <div class="flex items-start gap-3">
              <span class="text-xl shrink-0">{{ a.icon }}</span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                  <p class="text-xs font-bold" style="color:var(--text-primary)">{{ a.title }}</p>
                  <span class="text-xs px-2 py-0.5 rounded-full font-bold" :style="`background:rgba(${a.riskColor},0.1);color:rgb(${a.riskColor})`">{{ a.risk }}</span>
                </div>
                <p class="text-xs" style="color:var(--text-muted)">{{ a.desc }}</p>
                <div class="flex items-center gap-3 mt-2">
                  <span class="text-xs" style="color:var(--text-muted)">{{ a.time }}</span>
                  <button v-if="!a.resolved" @click="a.resolved = true" class="text-xs px-2 py-1 rounded-lg" style="background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)">Resolve</button>
                  <button class="text-xs px-2 py-1 rounded-lg" style="background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.15)">Ban User</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Flagged Listings -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(251,191,36,0.15)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Flagged Listings</h2>
        <div class="space-y-3">
          <div v-for="l in flaggedListings" :key="l.id" class="p-4 rounded-xl" style="background:rgba(251,191,36,0.03);border:1px solid rgba(251,191,36,0.1)">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs font-bold" style="color:var(--text-primary)">{{ l.title }}</p>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" style="background:rgba(251,191,36,0.1);color:#fbbf24">{{ l.reason }}</span>
            </div>
            <p class="text-xs mb-2" style="color:var(--text-muted)">{{ l.detail }}</p>
            <div class="flex gap-2">
              <button class="px-3 py-1 rounded-lg text-xs font-medium" style="background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)">Approve</button>
              <button class="px-3 py-1 rounded-lg text-xs font-medium" style="background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.15)">Remove</button>
              <button class="px-3 py-1 rounded-lg text-xs font-medium" style="background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)">Investigate</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from "vue"
const stats = [
  { label: "Active Alerts", value: "3", color: "#ef4444", trend: 1 },
  { label: "Flagged Listings", value: "5", color: "#fbbf24", trend: -2 },
  { label: "Banned Users (30d)", value: "2", color: "#a78bfa", trend: 0 },
  { label: "Auto-Blocked", value: "14", color: "#6fffd2", trend: 3 },
]
const alerts = ref([
  { id: 1, icon: "S", title: "Duplicate listing detected", desc: "Listing #HP-V-0021 appears 3x with different prices. Possible price manipulation.", time: "2h ago", risk: "HIGH", riskColor: "239,68,68", resolved: false },
  { id: 2, icon: "S", title: "Suspicious account activity", desc: "User mehmet_k4445 created 12 listings in 24h. Free plan limit: 3.", time: "5h ago", risk: "MEDIUM", riskColor: "251,191,36", resolved: false },
  { id: 3, icon: "S", title: "Stock photo detected", desc: "Listing #HP-P-0044 images match stock photo database. Unverified.", time: "1d ago", risk: "MEDIUM", riskColor: "251,191,36", resolved: false },
])
const flaggedListings = [
  { id: 1, title: "2015 Audi A4 — Lefkosa", reason: "Price Anomaly", detail: "Listed at 150K TRY vs market avg 280K TRY. Possibly fraudulent or test listing." },
  { id: 2, title: "3BR Villa Kyrenia — Sea View", reason: "Fake Photos", detail: "Reverse image search found images on stock photo sites." },
  { id: 3, title: "2021 Ford Focus — Girne", reason: "Duplicate", detail: "Same VIN found on 2 other active listings by different users." },
]
</script>