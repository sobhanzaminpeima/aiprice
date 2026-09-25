<template>
  <div class='p-8'>
    <div class='mb-8'>
      <h1 class='text-3xl font-black mb-1' style='color:var(--text-primary)'>Platform <span class='gradient-text'>Analytics</span></h1>
      <p class='text-sm' style='color:var(--text-muted)'>AI usage, token costs, and platform statistics</p>
    </div>
    <div v-if='loading' class='space-y-4'><div v-for='i in 4' :key='i' class='h-32 rounded-2xl animate-pulse' style='background:var(--bg-surface)'></div></div>
    <div v-else-if='error' class='rounded-2xl p-6 text-sm' style='background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);color:#ef4444'>{{ error }}</div>
    <div v-else>
      <div class='grid grid-cols-2 md:grid-cols-4 gap-4 mb-8'>
        <div v-for='s in statCards' :key='s.label' class='rounded-2xl p-5' style='background:var(--bg-surface);border:1px solid var(--border-subtle)'>
          <p class='text-2xl font-black mb-1' :style='{color:s.color}'>{{ s.value }}</p>
          <p class='text-xs' style='color:var(--text-muted)'>{{ s.label }}</p>
        </div>
      </div>
      <div class='rounded-2xl p-6' style='background:var(--bg-surface);border:1px solid var(--border-subtle)'>
        <h2 class='font-bold mb-5' style='color:var(--text-primary)'>AI Usage Logs</h2>
        <div v-if='aiLogs.length===0' class='text-center py-8' style='color:var(--text-muted)'>No AI usage logs yet</div>
        <table v-else class='w-full text-sm'>
          <thead><tr class='text-xs' style='color:var(--text-muted);border-bottom:1px solid var(--border-subtle)'><th class='text-left pb-3'>Purpose</th><th class='text-left pb-3'>Tokens</th><th class='text-left pb-3'>Cost</th><th class='text-left pb-3'>Status</th></tr></thead>
          <tbody>
            <tr v-for='log in aiLogs.slice(0,10)' :key='log.id' class='border-b' style='border-color:var(--surface-overlay)'>
              <td class='py-3 text-xs' style='color:var(--text-primary)'>{{ log.purpose }}</td>
              <td class='py-3 text-xs' style='color:var(--text-secondary)'>{{ log.total_tokens }}</td>
              <td class='py-3 text-xs font-medium' style='color:var(--accent-teal)'>£{{ log.cost }}</td>
              <td class='py-3 text-xs' :style="log.success?'color:var(--accent-teal)':'color:#ef4444'">{{ log.success ? 'success' : 'failed' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
<script setup lang='ts'>
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'

const loading = ref(true)
const error = ref('')
const aiLogs = ref<any[]>([])
const stats = ref<any>(null)

function compact(n: number) {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M'
  if (n >= 1_000) return (n / 1_000).toFixed(1) + 'K'
  return String(n)
}

const statCards = computed(() => [
  { label: 'AI Calls (this month)', value: compact(stats.value?.total_calls ?? 0), color: '#6fffd2' },
  { label: 'Tokens Used', value: compact(stats.value?.total_tokens ?? 0), color: '#56d8ff' },
  { label: 'AI Cost / mo', value: '£' + (stats.value?.cost ?? 0), color: '#a78bfa' },
  { label: 'Success Rate', value: (stats.value?.success_rate ?? 100) + '%', color: '#fbbf24' },
])

onMounted(async () => {
  try {
    const r = await client.get('/admin/analytics/ai-usage')
    aiLogs.value = r.data?.logs ?? []
    stats.value = r.data?.stats ?? null
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Could not load AI usage data.'
  } finally {
    loading.value = false
  }
})
</script>