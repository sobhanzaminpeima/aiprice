<template>
  <div class="space-y-4">
    <!-- Why? -->
    <div v-if="hasReasons" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
      <button @click="whyOpen = !whyOpen" class="w-full flex items-center justify-between text-left">
        <span class="font-bold text-sm flex items-center gap-2" style="color:var(--text-primary)">
          <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-black" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">AI</span>
          Why these scores?
        </span>
        <span style="color:var(--text-muted)">{{ whyOpen ? '−' : '+' }}</span>
      </button>
      <Transition name="expand">
        <div v-if="whyOpen" class="mt-4 space-y-3">
          <div v-for="group in reasonGroups" :key="group.label">
            <p class="text-xs font-bold mb-1.5" :style="{ color: group.color }">{{ group.label }}</p>
            <ul class="space-y-1">
              <li v-for="(r, i) in group.reasons" :key="i" class="text-xs flex items-start gap-1.5" style="color:var(--text-secondary)">
                <span style="color:var(--text-muted)">•</span> {{ r }}
              </li>
            </ul>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Show Me Better -->
    <div class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
      <button v-if="!better.length && !betterLoaded" @click="loadBetter" :disabled="loadingBetter"
        class="w-full py-2.5 rounded-xl text-sm font-bold transition" style="background:var(--surface-overlay);border:1px solid rgba(111,255,210,0.25);color:var(--accent-teal)">
        {{ loadingBetter ? 'Searching…' : '🔍 Show Me Better' }}
      </button>
      <template v-else-if="betterLoaded">
        <p v-if="!better.length" class="text-xs text-center py-2" style="color:var(--text-muted)">
          No alternatives beat this listing right now — it's already a strong option.
        </p>
        <template v-else>
          <p class="text-xs font-bold mb-3" style="color:var(--text-primary)">Found {{ better.length }} better match{{ better.length === 1 ? '' : 'es' }}</p>
          <RouterLink v-for="b in better" :key="b.id" :to="`/listings/${b.slug}`" class="block p-3 rounded-xl mb-2 last:mb-0 transition" style="background:var(--surface-overlay-soft);border:1px solid rgba(111,255,210,0.15)">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ b.title }}</span>
              <span class="text-xs font-black shrink-0 ml-2" style="color:var(--accent-teal)">{{ formatPrice(b.price, b.currency) }}</span>
            </div>
            <p v-for="(r, i) in b.reasons" :key="i" class="text-[11px]" style="color:var(--text-muted)">✓ {{ r }}</p>
          </RouterLink>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import type { Listing } from '@/types'

const props = defineProps<{ listing: Listing }>()

const whyOpen = ref(false)
const better = ref<{ id: string; slug: string; title: string; price: number; currency: string; reasons: string[] }[]>([])
const loadingBetter = ref(false)
const betterLoaded = ref(false)

const reasonGroups = computed(() => {
  const b = props.listing.ai_score_breakdown
  if (!b) return []
  const map = [
    { key: 'price', label: 'Price', color: '#6fffd2' },
    { key: 'trust', label: 'Trust', color: '#56d8ff' },
    { key: 'demand', label: 'Demand', color: '#a78bfa' },
    { key: 'investment', label: 'Investment', color: '#fbbf24' },
  ] as const
  return map
    .map(m => ({ ...m, reasons: (b as any)[m.key] as string[] | undefined }))
    .filter(g => g.reasons && g.reasons.length)
    .map(g => ({ ...g, reasons: g.reasons as string[] }))
})
const hasReasons = computed(() => reasonGroups.value.length > 0)

async function loadBetter() {
  loadingBetter.value = true
  try {
    const { data } = await client.get(`/listings/${props.listing.id}/better`)
    better.value = data.alternatives ?? []
  } catch {
    better.value = []
  } finally {
    loadingBetter.value = false
    betterLoaded.value = true
  }
}

function formatPrice(v: number, currency: string) {
  return Number(v).toLocaleString() + ' ' + currency
}
</script>

<style scoped>
.expand-enter-active, .expand-leave-active { transition: opacity 0.2s, max-height 0.25s; overflow: hidden; }
.expand-enter-from, .expand-leave-to { opacity: 0; max-height: 0; }
.expand-enter-to, .expand-leave-from { opacity: 1; max-height: 500px; }
</style>
