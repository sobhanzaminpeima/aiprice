<template>
  <section class="px-4 -mt-10 relative z-20 max-w-7xl mx-auto" :aria-label="$t('What AIPrice checks for you')">
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
      <RouterLink v-for="f in features" :key="f.title" :to="f.to" class="tile-glass rounded-2xl p-5 flex flex-col items-center text-center transition hover:-translate-y-0.5">
        <span class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background:rgba(18,197,232,.14);color:#4fd8f5"><AppIcon :name="f.icon" :size="24" /></span>
        <h3 class="font-bold text-sm mb-1" style="color:#fff">{{ $t(f.title) }}</h3>
        <p class="text-xs leading-snug" style="color:#a9bdd3">{{ $t(f.text) }}</p>
      </RouterLink>
    </div>
  </section>
</template>
<script setup lang="ts">
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'

const features = [
  { icon: 'shield', title: 'Fair Price Check', text: 'See the AI-estimated market value and know if a listing is fairly priced.', to: '/cars' },
  { icon: 'list', title: 'Similar Listings Comparison', text: 'Compare with real, up-to-date listings in North Cyprus.', to: '/search' },
  { icon: 'chart', title: 'Market Trend Insights', text: 'Track price changes, demand and market direction.', to: '/market-insights' },
  { icon: 'trending', title: 'Risk / Investment Signal', text: 'Understand investment potential and key risks with AI analysis.', to: '/investment-center' },
  { icon: 'gauge', title: 'Confidence Score', text: 'A clear, data-backed score showing how reliable the price is.', to: '/about' },
] as const
</script>
<style scoped>
.tile-glass { background: linear-gradient(160deg, rgba(14,42,72,.86), rgba(8,24,39,.9)); border: 1px solid rgba(120,190,255,.22); box-shadow: 0 14px 36px rgba(2,10,22,.3); text-decoration: none; }
</style>
