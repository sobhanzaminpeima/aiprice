<template>
  <section class="px-4 pb-16 max-w-7xl mx-auto" aria-labelledby="how-heading">
    <div class="how rounded-3xl p-6 md:p-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
      <div class="lg:col-span-4">
        <span class="eyebrow">✦ {{ $t('How AI price intelligence works') }}</span>
        <h2 id="how-heading" class="text-2xl md:text-3xl font-black mt-3 mb-3" style="color:var(--text-primary)">{{ $t('Turn listings into market intelligence') }}</h2>
        <p class="text-sm mb-5" style="color:var(--text-muted)">{{ $t('Our AI analyses comparable listings, price history and real market activity across North Cyprus to give you a clear, data-backed decision.') }}</p>
        <ul class="space-y-2.5">
          <li v-for="c in checks" :key="c" class="flex items-start gap-2 text-sm" style="color:var(--text-secondary)"><span class="tick">✓</span>{{ $t(c) }}</li>
        </ul>
      </div>

      <div class="lg:col-span-8">
        <div v-if="example" class="rounded-2xl p-4 md:p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle);box-shadow:0 10px 30px rgba(8,24,39,.08)">
          <RouterLink :to="`/listings/${example.slug}`" class="flex items-center gap-4 mb-4" style="text-decoration:none">
            <img v-if="example.thumbnail" :src="example.thumbnail" alt="" class="w-24 h-16 rounded-xl object-cover shrink-0" />
            <div class="min-w-0 flex-1">
              <p class="font-black truncate" style="color:var(--text-primary)">{{ example.title }}</p>
              <p class="text-xs" style="color:var(--text-muted)">{{ example.city || '—' }}, North Cyprus</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-[11px]" style="color:var(--text-muted)">{{ $t('Listed price') }}</p>
              <p class="text-xl font-black" style="color:var(--text-primary)">{{ money(example.price) }}</p>
            </div>
          </RouterLink>
          <AiDealAnalysis :listing="example" />
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div v-for="(s, i) in steps" :key="s.t" class="rounded-2xl p-5" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm mb-3" style="background:rgba(var(--accent-cyan-rgb),.12);color:var(--accent-cyan)">{{ i + 1 }}</span>
            <h3 class="font-bold text-sm mb-1" style="color:var(--text-primary)">{{ $t(s.t) }}</h3>
            <p class="text-xs" style="color:var(--text-muted)">{{ $t(s.d) }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
<script setup lang="ts">
import { RouterLink } from 'vue-router'
import AiDealAnalysis from '@/components/listings/AiDealAnalysis.vue'
import type { Listing } from '@/types'

const props = defineProps<{ example: Listing | null }>()
const checks = ['Analyses real, up-to-date listings', 'Calculates a fair price range', 'Shows the market trend and price direction', 'Gives a confidence score and a deal signal']
const steps = [
  { t: 'Similar listings', d: 'We find listings like this one by model, year, size and location.' },
  { t: 'Fair price range', d: 'The typical asking price and range come from those real prices.' },
  { t: 'Signal and confidence', d: 'You see if the price is fair and how much data backs it.' },
]
const money = (v: number) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: props.example?.currency || 'GBP', maximumFractionDigits: 0 }).format(v)
</script>
<style scoped>
.how { background: linear-gradient(160deg, #eef5fc, #f7fbff); border: 1px solid var(--border-subtle); }
:root[data-theme="dark"] .how { background: var(--bg-surface); }
.eyebrow { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--accent-cyan);background:rgba(var(--accent-cyan-rgb),.1) }
.tick { color:var(--accent-teal);font-weight:900 }
</style>
