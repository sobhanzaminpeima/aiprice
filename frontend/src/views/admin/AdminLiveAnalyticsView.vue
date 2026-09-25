<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Live <span class="gradient-text">Analytics</span></h1>
        <p class="text-sm flex items-center gap-2" style="color:var(--text-muted)">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#6fffd2;animation:pulse-glow 1.5s infinite"></span>
          Real-time data — updates every 5 seconds
        </p>
      </div>
      <div class="text-right">
        <p class="text-2xl font-black" style="color:var(--accent-teal)">{{ liveVisitors }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Live Visitors</p>
      </div>
    </div>

    <!-- Live metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div v-for="m in metrics" :key="m.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center justify-between mb-2">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" :style="'background:rgba('+m.rgb+',0.12)'">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" :stroke="'rgb('+m.rgb+')'" stroke-width="1.8" v-html="m.iconPath"></svg>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full" :style="m.up ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal)' : 'background:rgba(239,68,68,0.1);color:#ef4444'">
            {{ m.up ? '↑' : '↓' }} {{ m.change }}
          </span>
        </div>
        <p class="text-2xl font-black" :style="{color: m.color}">{{ m.value }}</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">{{ m.label }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Live Activity Feed -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5 flex items-center gap-2" style="color:var(--text-primary)">
          <span class="w-2 h-2 rounded-full" style="background:#6fffd2;animation:pulse-glow 1.5s infinite"></span>
          Live Activity Feed
        </h2>
        <div class="space-y-3 max-h-80 overflow-y-auto">
          <div v-for="(event, i) in activityFeed" :key="i" class="flex items-start gap-3 p-3 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" :style="'background:'+event.bg">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" :stroke="event.color" stroke-width="2" v-html="event.iconPath"></svg>
          </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-medium" style="color:var(--text-primary)">{{ event.text }}</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ event.time }}</p>
            </div>
            <span class="text-xs px-1.5 py-0.5 rounded font-medium shrink-0" :style="`background:${event.bg};color:${event.color}`">{{ event.tag }}</span>
          </div>
        </div>
      </div>

      <!-- Pages Right Now -->
      <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Pages Right Now</h2>
        <div class="space-y-3">
          <div v-for="page in topPages" :key="page.path" class="flex items-center gap-3">
            <span class="text-xs font-mono w-36 truncate" style="color:var(--text-secondary)">{{ page.path }}</span>
            <div class="flex-1 h-2 rounded-full" style="background:var(--border-subtle)">
              <div class="h-2 rounded-full transition-all duration-1000" :style="`width:${page.pct}%;background:linear-gradient(90deg,#6fffd2,#56d8ff)`"></div>
            </div>
            <span class="text-xs font-bold w-6 text-right" style="color:var(--accent-teal)">{{ page.visitors }}</span>
          </div>
        </div>

        <div class="mt-6 pt-5" style="border-top:1px solid var(--border-subtle)">
          <h3 class="text-sm font-semibold mb-4" style="color:var(--text-secondary)">Traffic by City</h3>
          <div class="space-y-2">
            <div v-for="city in trafficByCities" :key="city.name" class="flex items-center gap-3">
              <span class="text-xs w-20" style="color:var(--text-secondary)">{{ city.name }}</span>
              <div class="flex-1 h-1.5 rounded-full" style="background:var(--border-subtle)">
                <div class="h-1.5 rounded-full" :style="`width:${city.pct}%;background:${city.color}`"></div>
              </div>
              <span class="text-xs w-8 text-right" style="color:var(--text-muted)">{{ city.pct }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const liveVisitors = ref(47)
let timer: ReturnType<typeof setInterval>

const metrics = ref([
  { iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>', rgb: '111,255,210', label: 'Active Sessions', value: '47', color: '#6fffd2', up: true, change: '12%' },
  { iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>', rgb: '86,216,255', label: 'Page Views / Hr', value: '384', color: '#56d8ff', up: true, change: '8%' },
  { iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>', rgb: '167,139,250', label: 'New Listings Today', value: '3', color: '#a78bfa', up: true, change: '50%' },
  { iconPath: '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>', rgb: '251,191,36', label: 'Inquiries / Hr', value: '12', color: '#fbbf24', up: false, change: '3%' },
])

const CAR_PATH = 'M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6'
const HOME_PATH = 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6'
const USER_PATH = 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
const MSG_PATH = 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'
const SHIELD_PATH = 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
const STAR_PATH = 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'
const AI_PATH = 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'
const CARD_PATH = 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'

const activityFeed = ref([
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${CAR_PATH}"/>`, text: 'New vehicle listing: 2020 BMW 3 Series — Girne', time: 'Just now', tag: 'NEW', bg: 'rgba(111,255,210,0.1)', color: '#6fffd2' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${USER_PATH}"/>`, text: 'New user registered: mehmet.k@email.com', time: '2 min ago', tag: 'USER', bg: 'rgba(86,216,255,0.1)', color: '#56d8ff' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${MSG_PATH}"/>`, text: 'Inquiry sent on: 2019 Toyota Corolla — Lefkosa', time: '4 min ago', tag: 'MSG', bg: 'rgba(167,139,250,0.1)', color: '#a78bfa' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${HOME_PATH}"/>`, text: 'New property listing: 3BR Villa in Kyrenia', time: '7 min ago', tag: 'NEW', bg: 'rgba(111,255,210,0.1)', color: '#6fffd2' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${SHIELD_PATH}"/>`, text: 'Fraud alert: Suspicious listing flagged #HP-V-0021', time: '11 min ago', tag: 'FRAUD', bg: 'rgba(239,68,68,0.1)', color: '#ef4444' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${STAR_PATH}"/>`, text: 'Listing featured by admin: Honda Civic 2019', time: '15 min ago', tag: 'ADMIN', bg: 'rgba(251,191,36,0.1)', color: '#fbbf24' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${AI_PATH}"/>`, text: 'AI analysis completed: Trust score 94 assigned', time: '18 min ago', tag: 'AI', bg: 'rgba(111,255,210,0.1)', color: '#6fffd2' },
  { iconPath: `<path stroke-linecap="round" stroke-linejoin="round" d="${CARD_PATH}"/>`, text: 'New subscription: Dealer Plan — autopro.cy', time: '24 min ago', tag: 'SUB', bg: 'rgba(86,216,255,0.1)', color: '#56d8ff' },
])

const topPages = ref([
  { path: '/', visitors: 18, pct: 100 },
  { path: '/cars', visitors: 12, pct: 67 },
  { path: '/properties', visitors: 8, pct: 44 },
  { path: '/listings/bmw-x3', visitors: 5, pct: 28 },
  { path: '/login', visitors: 4, pct: 22 },
])

const trafficByCities = [
  { name: 'Girne', pct: 34, color: '#6fffd2' },
  { name: 'Lefkosa', pct: 28, color: '#56d8ff' },
  { name: 'Famagusta', pct: 18, color: '#a78bfa' },
  { name: 'Iskele', pct: 12, color: '#fbbf24' },
  { name: 'Other', pct: 8, color: 'var(--text-muted)' },
]

onMounted(() => {
  timer = setInterval(() => {
    liveVisitors.value = Math.max(30, liveVisitors.value + Math.floor(Math.random() * 5) - 2)
    metrics.value[0].value = String(liveVisitors.value)
  }, 5000)
})
onUnmounted(() => clearInterval(timer))
</script>
