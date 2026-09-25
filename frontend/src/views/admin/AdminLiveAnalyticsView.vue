<template>
  <div class="p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Live <span class="gradient-text">Activity</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Refreshes every 15 seconds. Visitors are distinct IPs viewing listings — an approximation, not a login count.</p>
      </div>
      <span class="flex items-center gap-2 text-xs" :style="stale ? 'color:#fbbf24' : 'color:var(--accent-teal)'">
        <span class="w-2 h-2 rounded-full" :class="stale ? '' : 'animate-pulse'" :style="`background:${stale ? '#fbbf24' : '#6fffd2'}`"></span>
        {{ stale ? 'Reconnecting…' : `Updated ${updatedAgo}` }}
      </span>
    </div>

    <div v-if="error && !data" class="rounded-2xl p-4 mb-6 text-sm" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</div>

    <template v-if="data">
      <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
        <div v-for="m in metricCards" :key="m.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <p class="text-2xl font-black mb-1" :style="{ color: m.color }">{{ m.value }}</p>
          <p class="text-xs" style="color:var(--text-muted)">{{ m.label }}</p>
        </div>
      </div>

      <div class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">Listing views per minute (last 30 min)</h2>
        <div class="flex items-end gap-1 h-32">
          <div v-for="p in data.views_per_minute" :key="p.minute" class="flex-1 rounded-t" :title="`${p.minute}: ${p.views} views`" :style="`height:${maxViews ? Math.max(3, (p.views / maxViews) * 100) : 3}%;background:${p.views ? 'linear-gradient(to top,#6fffd2,#56d8ff)' : 'var(--border-subtle)'}`"></div>
        </div>
        <div class="flex justify-between text-[10px] mt-1" style="color:var(--text-muted)"><span>{{ data.views_per_minute[0]?.minute }}</span><span>now</span></div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-4" style="color:var(--text-primary)">Most viewed — last hour</h2>
          <p v-if="!data.top_listings_1h.length" class="text-sm" style="color:var(--text-muted)">No listing views in the last hour.</p>
          <div v-else class="space-y-2">
            <RouterLink v-for="t in data.top_listings_1h" :key="t.listing.id" :to="`/listings/${t.listing.slug}`" target="_blank" class="flex items-center gap-3 p-2 rounded-lg hover:opacity-80">
              <span class="w-10 text-right text-sm font-black" style="color:var(--accent-teal)">{{ t.views }}</span>
              <span class="flex-1 text-sm truncate" style="color:var(--text-primary)">{{ t.listing.title }}</span>
              <span class="text-xs" style="color:var(--text-muted)">{{ t.listing.city }}</span>
            </RouterLink>
          </div>
        </div>

        <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="font-bold mb-4" style="color:var(--text-primary)">Recent events</h2>
          <div class="space-y-2">
            <div v-for="(e, i) in data.feed" :key="i" class="flex items-start gap-3 text-xs">
              <span>{{ icons[e.type] }}</span>
              <span class="flex-1" style="color:var(--text-secondary)">{{ e.text }}<span v-if="e.status" class="ml-1 capitalize" style="color:var(--text-muted)">({{ e.status }})</span></span>
              <span style="color:var(--text-muted)">{{ ago(e.at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const icons: Record<string, string> = { signup: '👤', listing: '🏷️', inquiry: '💬' }
const data = ref<any>(null)
const error = ref('')
const stale = ref(false)
const now = ref(Date.now())
let timer: number | undefined
let clock: number | undefined

const maxViews = computed(() => Math.max(0, ...(data.value?.views_per_minute ?? []).map((p: any) => p.views)))
const updatedAgo = computed(() => {
  if (!data.value) return ''
  const s = Math.max(0, Math.round((now.value - new Date(data.value.at).getTime()) / 1000))
  return s < 5 ? 'just now' : `${s}s ago`
})
const metricCards = computed(() => {
  const m = data.value?.metrics ?? {}
  return [
    { label: 'Visitors (5 min)', value: m.active_visitors_5m ?? 0, color: '#6fffd2' },
    { label: 'Views (30 min)', value: m.views_30m ?? 0, color: '#56d8ff' },
    { label: 'Views today', value: m.views_today ?? 0, color: '#a78bfa' },
    { label: 'Sign-ups today', value: m.signups_today ?? 0, color: '#fbbf24' },
    { label: 'New listings today', value: m.listings_today ?? 0, color: '#f472b6' },
    { label: 'Inquiries today', value: m.inquiries_today ?? 0, color: '#34d399' },
  ]
})

async function refresh() {
  if (document.hidden) return
  try {
    data.value = (await client.get('/admin/live/snapshot')).data
    stale.value = false
    error.value = ''
  } catch (e) {
    stale.value = true
    error.value = apiErrorMessage(e, 'Could not load live data.')
  }
}

function ago(iso: string) {
  const s = Math.floor((now.value - new Date(iso).getTime()) / 1000)
  if (s < 60) return 'now'
  if (s < 3600) return `${Math.floor(s / 60)}m`
  if (s < 86400) return `${Math.floor(s / 3600)}h`
  return `${Math.floor(s / 86400)}d`
}

onMounted(() => {
  refresh()
  timer = window.setInterval(refresh, 15_000)
  clock = window.setInterval(() => (now.value = Date.now()), 1000)
  document.addEventListener('visibilitychange', refresh)
})
onBeforeUnmount(() => {
  window.clearInterval(timer)
  window.clearInterval(clock)
  document.removeEventListener('visibilitychange', refresh)
})
</script>
