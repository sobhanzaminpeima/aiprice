<template>
  <section class="px-4 pb-14 max-w-7xl mx-auto" :aria-labelledby="`seo-${kind}`">
    <div class="rounded-3xl p-6 md:p-8 grid grid-cols-1 lg:grid-cols-2 gap-8" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
      <div>
        <h2 :id="`seo-${kind}`" class="text-xl font-black mb-3" style="color:var(--text-primary)">{{ $t(c.title) }}</h2>
        <p v-for="p in c.paragraphs" :key="p" class="text-sm leading-relaxed mb-3" style="color:var(--text-secondary)">{{ $t(p) }}</p>
        <h3 class="text-sm font-bold mt-5 mb-2" style="color:var(--text-primary)">{{ $t('Popular searches') }}</h3>
        <div class="flex flex-wrap gap-2">
          <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="pill">{{ l.label }}</RouterLink>
        </div>
      </div>
      <div>
        <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary)">{{ $t('Frequently asked questions') }}</h3>
        <details v-for="q in c.faq" :key="q[0]" class="rounded-xl p-3 mb-2" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
          <summary class="text-sm font-semibold cursor-pointer" style="color:var(--text-primary)">{{ $t(q[0]) }}</summary>
          <p class="text-sm mt-2" style="color:var(--text-muted)">{{ $t(q[1]) }}</p>
        </details>
      </div>
    </div>
  </section>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { t } from '@/i18n'
import { SEO_CONTENT } from '@/components/seoContent'

const props = defineProps<{ kind: 'home' | 'vehicle' | 'property' }>()
const c = computed(() => SEO_CONTENT[props.kind])
const cities = [['Girne', 'Girne (Kyrenia)'], ['Lefkosa', 'Lefkoşa (Nicosia)'], ['Gazimağusa', 'Gazimağusa (Famagusta)'], ['Iskele', 'İskele'], ['Guzelyurt', 'Güzelyurt'], ['Lapta', 'Lapta']] as const
const links = computed(() => {
  const out: { to: string; label: string }[] = []
  if (props.kind !== 'property') for (const [q, name] of cities) out.push({ to: `/cars?city=${encodeURIComponent(q)}`, label: t('Cars in {city}', { city: name }) })
  if (props.kind !== 'vehicle') for (const [q, name] of cities) out.push({ to: `/properties?city=${encodeURIComponent(q)}`, label: t('Property in {city}', { city: name }) })
  if (props.kind === 'vehicle') for (const b of ['Toyota', 'Mercedes', 'BMW', 'Volkswagen', 'Honda', 'Nissan']) out.push({ to: `/cars?brand=${b}`, label: t('Used {brand} for sale', { brand: b }) })
  if (props.kind === 'property') for (const ty of ['Villa', 'Apartment', 'Land', 'Penthouse']) out.push({ to: `/properties?type=${ty}`, label: t('{type} for sale', { type: t(ty) }) })
  return out
})
</script>
<style scoped>
.pill { padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;color:var(--text-secondary);border:1px solid var(--border-strong);text-decoration:none }
.pill:hover { color:var(--accent-cyan);border-color:var(--accent-cyan) }
</style>
