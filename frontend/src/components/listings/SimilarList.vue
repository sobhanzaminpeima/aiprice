<template>
  <div class="rounded-2xl p-5 h-full" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
    <h3 class="font-bold text-sm mb-3" style="color:var(--text-primary)">{{ $t(title) }} <span class="font-normal text-xs" style="color:var(--text-muted)">{{ $t('(same model and market)') }}</span></h3>
    <p v-if="!items.length" class="text-xs" style="color:var(--text-muted)">{{ $t('No similar listings yet.') }}</p>
    <RouterLink v-for="c in items.slice(0, 3)" :key="c.id" :to="`/listings/${c.slug}`" class="flex items-center gap-3 py-2.5" style="border-top:1px solid var(--border-subtle);text-decoration:none">
      <img v-if="thumb(c)" :src="thumb(c)!" alt="" class="w-14 h-10 rounded-lg object-cover shrink-0" loading="lazy" />
      <span v-else class="w-14 h-10 rounded-lg shrink-0" style="background:var(--bg-elevated)"></span>
      <span class="min-w-0 flex-1">
        <span class="block text-xs font-bold truncate" style="color:var(--text-primary)">{{ c.title }}</span>
        <span class="block text-[11px]" style="color:var(--text-muted)">{{ c.city || '—' }}, North Cyprus</span>
      </span>
      <span class="price">{{ money(c.price, c.currency) }}</span>
    </RouterLink>
  </div>
</template>
<script setup lang="ts">
import { RouterLink } from 'vue-router'
defineProps<{ items: any[]; title: string }>()
const thumb = (c: any): string | null => c.thumbnail || (Array.isArray(c.images) ? c.images[0] : null) || null
const money = (v: number, cur: string) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur || 'GBP', maximumFractionDigits: 0 }).format(v)
</script>
<style scoped>
.price { flex-shrink:0;padding:4px 10px;border-radius:10px;font-size:12px;font-weight:800;color:var(--accent-teal);background:rgba(var(--accent-teal-rgb),.1) }
</style>
