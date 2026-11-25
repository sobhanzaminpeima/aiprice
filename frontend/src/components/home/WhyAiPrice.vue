<template>
  <section class="px-4 pb-16 max-w-7xl mx-auto" aria-labelledby="why-heading">
    <p class="text-xs font-bold tracking-wider uppercase mb-1" style="color:var(--accent-cyan)">{{ $t('Why AIPrice?') }}</p>
    <h2 id="why-heading" class="text-2xl sm:text-3xl font-black mb-8" style="color:var(--text-primary)">{{ $t('More than a marketplace — a smarter way to buy, sell and invest') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="w in reasons" :key="w.title" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
        <span class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background:rgba(var(--accent-cyan-rgb),.1);color:var(--accent-cyan)"><AppIcon :name="w.icon" :size="22" /></span>
        <h3 class="font-bold text-sm mb-1" style="color:var(--text-primary)">{{ $t(w.title) }}</h3>
        <p class="text-xs leading-relaxed" style="color:var(--text-muted)">{{ $t(w.text) }}</p>
      </div>
    </div>
  </section>
</template>
<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
const reasons = [
  { icon: 'cpu', title: 'AI-powered valuation', text: 'Know the real market value, not just the asking price.' },
  { icon: 'list', title: 'Smarter comparison', text: 'Compare similar listings with real market data.' },
  { icon: 'chart', title: 'Investment clarity', text: 'Spot high-potential opportunities and understand the risks.' },
  { icon: 'shield', title: 'Buy and sell with confidence', text: 'Decisions backed by data, trends and AI insights.' },
] as const
</script>
