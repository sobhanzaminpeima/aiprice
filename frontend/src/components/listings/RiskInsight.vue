<template>
  <div v-if="listing.risk_level" class="rounded-2xl p-5 h-full" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
    <h3 class="font-bold text-sm mb-3 flex items-center gap-2" style="color:var(--text-primary)">
      <AppIcon name="shield" :size="18" /> {{ $t(listing.type === 'vehicle' ? 'Risk insight' : 'Investment / risk insight') }}
    </h3>
    <span class="inline-block px-3 py-1 rounded-full text-sm font-black mb-3" :style="badge">{{ $t(label) }}</span>
    <p class="text-xs leading-relaxed" style="color:var(--text-secondary)">{{ $t(listing.risk_note || '') }}</p>
    <p v-if="listing.type !== 'vehicle' && listing.ai_investment_score" class="text-xs mt-3" style="color:var(--text-muted)">
      {{ $t('Investment score') }}: <strong style="color:var(--text-primary)">{{ Math.round(Number(listing.ai_investment_score)) }}/100</strong>
    </p>
    <p class="text-[11px] mt-3" style="color:var(--text-muted)">{{ $t('Guidance from public data. Not financial advice or a verdict on the seller.') }}</p>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { Listing } from '@/types'

const props = defineProps<{ listing: Listing }>()
const label = computed(() => ({ low: 'Low risk', medium: 'Medium risk', high: 'High risk' } as Record<string, string>)[props.listing.risk_level ?? ''] ?? '')
const badge = computed(() => {
  const c = props.listing.risk_level === 'low' ? 'accent-teal' : props.listing.risk_level === 'medium' ? 'warning' : 'danger'
  const rgb = c === 'accent-teal' ? 'accent-teal-rgb' : c === 'warning' ? 'warning-rgb' : 'danger-rgb'
  return `background:rgba(var(--${rgb}),0.12);color:var(--${c});border:1px solid rgba(var(--${rgb}),0.3)`
})
</script>
