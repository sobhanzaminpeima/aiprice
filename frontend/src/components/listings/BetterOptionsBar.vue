<template>
  <div class="rounded-2xl p-4" style="background:rgba(var(--accent-cyan-rgb),.06);border:1px solid rgba(var(--accent-cyan-rgb),.25)">
    <button type="button" class="w-full flex items-center gap-3 text-left" @click="load" :disabled="loading">
      <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(var(--accent-cyan-rgb),.12);color:var(--accent-cyan)">⌕</span>
      <span class="flex-1">
        <span class="block font-bold text-sm" style="color:var(--text-primary)">{{ $t('See better AI-rated options') }}</span>
        <span class="block text-xs" style="color:var(--text-muted)">{{ loading ? $t('Searching…') : $t('Similar listings with a better price, deal signal or risk score.') }}</span>
      </span>
      <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:var(--accent-cyan);color:#fff">→</span>
    </button>
    <div v-if="loaded" class="mt-3">
      <p v-if="!items.length" class="text-xs" style="color:var(--text-muted)">{{ $t('No better alternatives right now.') }}</p>
      <RouterLink v-for="b in items" :key="b.id" :to="`/listings/${b.slug}`" class="block p-3 rounded-xl mt-2" style="background:var(--bg-card);border:1px solid var(--border-subtle);text-decoration:none">
        <span class="flex items-center justify-between gap-2"><span class="text-xs font-bold truncate" style="color:var(--text-primary)">{{ b.title }}</span><span class="text-xs font-black" style="color:var(--accent-teal)">{{ money(b.price, b.currency) }}</span></span>
        <span v-for="(r, i) in b.reasons" :key="i" class="block text-[11px]" style="color:var(--text-muted)">✓ {{ r }}</span>
      </RouterLink>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'

const props = defineProps<{ listingId: string }>()
const items = ref<{ id: string; slug: string; title: string; price: number; currency: string; reasons: string[] }[]>([])
const loading = ref(false)
const loaded = ref(false)
async function load() {
  if (loaded.value || loading.value) return
  loading.value = true
  try { items.value = (await client.get(`/listings/${props.listingId}/better`)).data.alternatives ?? [] } catch { items.value = [] }
  finally { loading.value = false; loaded.value = true }
}
const money = (v: number, cur: string) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur || 'GBP', maximumFractionDigits: 0 }).format(v)
</script>
