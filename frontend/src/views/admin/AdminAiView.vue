<template>
  <div class='p-8'>
    <div class='mb-8'>
      <h1 class='text-3xl font-black mb-1' style='color:var(--text-primary)'>AI <span class='gradient-text'>Intelligence</span></h1>
      <p class='text-sm' style='color:var(--text-muted)'>Configure AI models and monitor usage</p>
    </div>
    <div v-if='loading' class='space-y-3'><div v-for='i in 4' :key='i' class='h-20 rounded-2xl animate-pulse' style='background:var(--bg-surface)'></div></div>
    <div v-else class='space-y-3'>
      <div v-for='m in models' :key='m.id' class='rounded-2xl p-5 flex items-center justify-between' style='background:var(--bg-surface);border:1px solid var(--border-subtle)'>
        <div>
          <div class='flex items-center gap-3 mb-1'>
            <span class='ai-badge'>{{ m.provider }}</span>
            <h3 class='font-bold text-sm' style='color:var(--text-primary)'>{{ m.name }}</h3>
            <span v-if='m.is_default' class='text-xs' style='color:#fbbf24'>Default</span>
          </div>
          <p class='text-xs' style='color:var(--text-muted)'>{{ m.model_id }} &middot; {{ m.purpose }} &middot; £{{ m.cost_per_1k_tokens }}/1k tokens</p>
        </div>
        <div class='flex items-center gap-4'>
          <span class='text-xs font-medium' :style="m.is_active?'color:var(--accent-teal)':'color:#ef4444'">{{ m.is_active?'Active':'Inactive' }}</span>
          <button @click='toggleModel(m)' class='px-3 py-1.5 text-xs rounded-lg font-medium transition-all'
            :style="m.is_active?'background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.2)':'background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)'">
            {{ m.is_active?'Disable':'Enable' }}
          </button>
        </div>
      </div>
      <div v-if='models.length===0' class='text-center py-16 rounded-2xl' style='background:var(--bg-surface);border:1px solid var(--border-subtle)'>
        <div class='text-5xl mb-3'>R</div>
        <p class='font-semibold' style='color:var(--text-primary)'>No AI models configured</p>
        <p class='text-xs mt-1' style='color:var(--text-muted)'>Add AI model configurations in the backend</p>
      </div>
    </div>
  </div>
</template>
<script setup lang='ts'>
import { ref, onMounted } from 'vue'; import client from '@/api/client'
const models = ref<any[]>([]); const loading = ref(true)
onMounted(async () => { try { const r = await client.get('/admin/ai-models'); models.value = r.data||[] } catch { models.value=[] } finally { loading.value=false } })
async function toggleModel(m:any) { try { await client.put('/admin/ai-models/'+m.id, {is_active:!m.is_active}); m.is_active=!m.is_active } catch {} }
</script>