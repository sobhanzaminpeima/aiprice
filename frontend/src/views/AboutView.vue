<template>
  <MainLayout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-20 md:py-24">
      <h1 class="text-4xl md:text-5xl font-black mb-6" style="color:var(--text-primary)">About <span class="gradient-text">AIPrice.app</span></h1>
      <p class="text-lg md:text-xl mb-4 max-w-3xl" style="color:var(--text-secondary)">
        AIPrice.app is a marketplace for cars and property in North Cyprus that shows you what a listing is really worth — before you call the seller.
      </p>
      <p class="mb-16 max-w-3xl" style="color:var(--text-muted)">
        Asking prices here vary wildly and comparable sales are hard to find. We compare every listing against similar ones in the same city and put the verdict right on the page, so buyers negotiate with facts and honest sellers stand out.
      </p>

      <h2 class="text-2xl font-black mb-6" style="color:var(--text-primary)">What every listing shows</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-16">
        <div v-for="s in scores" :key="s.title" class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <p class="text-2xl mb-3">{{ s.icon }}</p>
          <p class="font-bold mb-2" style="color:var(--text-primary)">{{ s.title }}</p>
          <p class="text-sm leading-relaxed" style="color:var(--text-muted)">{{ s.text }}</p>
        </div>
      </div>

      <h2 class="text-2xl font-black mb-6" style="color:var(--text-primary)">How we keep it safe</h2>
      <ul class="space-y-3 mb-16 max-w-3xl">
        <li v-for="t in trust" :key="t" class="flex gap-3 text-sm" style="color:var(--text-secondary)"><span style="color:var(--accent-teal)">✓</span>{{ t }}</li>
      </ul>

      <div class="rounded-2xl p-8 flex flex-col md:flex-row md:items-center justify-between gap-6" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.08),rgba(var(--accent-cyan-rgb),0.06));border:1px solid rgba(var(--accent-teal-rgb),0.2)">
        <div>
          <p class="text-xl font-black mb-1" style="color:var(--text-primary)">Selling a car or a home?</p>
          <p class="text-sm" style="color:var(--text-muted)">List for free and get an instant AI price check.</p>
        </div>
        <div class="flex gap-3">
          <RouterLink to="/register" class="px-6 py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Get started</RouterLink>
          <RouterLink to="/contact" class="px-6 py-3 rounded-xl text-sm font-semibold" style="color:var(--text-primary);border:1px solid var(--border-strong)">Contact us</RouterLink>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import { usePageMeta } from '@/composables/usePageMeta'

usePageMeta(() => ({
  title: 'About',
  description: 'AIPrice.app compares every car and property listing in North Cyprus against the market, so you know the fair price before you buy or sell.',
}))

const scores = [
  { icon: '⚖️', title: 'Fair-price verdict', text: 'Where the asking price sits against similar listings in the same city — fair, a good deal, or overpriced.' },
  { icon: '🛡️', title: 'Trust score', text: 'How complete and verifiable the listing and seller are: identity checks, photos, account history.' },
  { icon: '📈', title: 'Demand & investment', text: 'How much interest the listing is getting, and for property, how it looks as an investment.' },
]

const trust = [
  'Sellers can verify their identity; verified sellers are marked on their listings.',
  'Every listing is reviewed before it goes live.',
  'Buyer and seller contact details are never shown publicly — our team connects you.',
  'Reported listings go to a fraud team, and risky patterns are flagged automatically.',
]
</script>
