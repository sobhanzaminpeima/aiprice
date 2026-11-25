<template>
  <section class="engine rounded-3xl p-5 md:p-7 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center" :aria-label="$t('AI Decision Engine')">
    <div class="lg:col-span-4">
      <span class="chip-ai">✦ {{ $t('AI Decision Engine') }}</span>
      <h2 class="text-2xl md:text-3xl font-black mt-3 mb-2 leading-tight" style="color:#fff">{{ $t('Search, analyse and find the best deal') }}</h2>
      <p class="text-sm" style="color:#b9cde2">{{ $t('More than a search — get AI-powered price insights, market context and smarter recommendations.') }}</p>
    </div>

    <div class="lg:col-span-8">
      <div class="flex flex-wrap gap-2 mb-4" role="tablist">
        <button v-for="t in tabs" :key="t.value" role="tab" :aria-selected="tab === t.value" @click="tab = t.value" class="tab" :class="{ 'tab--on': tab === t.value }">
          <AssetIcon :type="t.value as any" :size="16" /> {{ $t(t.label) }}
        </button>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
        <label class="field">
          <span>{{ $t(tab === 'vehicle' ? 'Make / model' : 'Property type') }}</span>
          <select v-if="tab === 'vehicle'" v-model="brand"><option value="">{{ $t('Any make / model') }}</option><option v-for="b in brands" :key="b" :value="b">{{ b }}</option></select>
          <select v-else v-model="propertyType"><option value="">{{ $t('Any type') }}</option><option v-for="p in propertyTypes" :key="p" :value="p">{{ $t(p) }}</option></select>
        </label>
        <label class="field">
          <span>{{ $t(tab === 'vehicle' ? 'Year' : 'Bedrooms') }}</span>
          <select v-if="tab === 'vehicle'" v-model="minYear"><option value="">{{ $t('Any year') }}</option><option v-for="y in years" :key="y" :value="y">{{ y }}+</option></select>
          <select v-else v-model="beds"><option value="">{{ $t('Any') }}</option><option v-for="n in [1, 2, 3, 4, 5]" :key="n" :value="String(n)">{{ n }}+</option></select>
        </label>
        <label class="field">
          <span>{{ $t('Price range') }}</span>
          <select v-model="maxPrice"><option value="">{{ $t('Any price') }}</option><option v-for="p in priceSteps" :key="p" :value="String(p)">≤ £{{ p.toLocaleString('en-GB') }}</option></select>
        </label>
        <label class="field">
          <span>{{ $t('Location') }}</span>
          <select v-model="city"><option value="">{{ $t('All North Cyprus') }}</option><option v-for="c in cities" :key="c.q" :value="c.q">{{ c.label }}</option></select>
        </label>
        <button @click="go" class="find-btn col-span-2 md:col-span-2">✦ {{ $t('Find best deals with AI') }} →</button>
      </div>

      <div class="flex flex-wrap gap-2 mt-4">
        <RouterLink v-for="q in quick" :key="q.label" :to="q.to" class="quick"><AppIcon :name="q.icon" :size="14" /> {{ $t(q.label) }}</RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/ui/AppIcon.vue'
import AssetIcon from '@/components/ui/AssetIcon.vue'

const router = useRouter()
const auth = useAuthStore()

const tabs = [{ value: 'vehicle', label: 'Cars' }, { value: 'property', label: 'Properties' }, { value: 'rental', label: 'Rentals' }] as const
const tab = ref<'vehicle' | 'property' | 'rental'>('vehicle')
const brand = ref(''), propertyType = ref(''), minYear = ref(''), beds = ref(''), maxPrice = ref(''), city = ref('')
const brands = ref<string[]>([])
const propertyTypes = ['Apartment', 'Villa', 'Townhouse', 'Penthouse', 'Studio', 'Duplex', 'Land', 'Commercial']
const cities = [
  { label: 'Girne (Kyrenia)', q: 'Girne' }, { label: 'Lefkoşa (Nicosia)', q: 'Lefkosa' }, { label: 'Gazimağusa (Famagusta)', q: 'Gazimağusa' },
  { label: 'İskele', q: 'Iskele' }, { label: 'Güzelyurt', q: 'Guzelyurt' }, { label: 'Lapta', q: 'Lapta' },
]
const thisYear = new Date().getFullYear()
const years = Array.from({ length: 16 }, (_, i) => thisYear - i * 2)
const priceSteps = computed(() => (tab.value === 'vehicle' ? [5000, 10000, 15000, 25000, 40000] : tab.value === 'rental' ? [500, 750, 1000, 1500, 2500] : [75000, 150000, 250000, 400000, 750000]))
const quick = [
  { icon: 'cpu', label: 'Get AI valuation', to: '/dashboard/listings/new' },
  { icon: 'list', label: 'Compare similar listings', to: '/search' },
  { icon: 'trending', label: 'See market trends', to: '/market-insights' },
  { icon: 'shield', label: 'Check investment potential', to: '/investment-center' },
] as const

onMounted(async () => {
  try { brands.value = ((await client.get('/vehicle-brands')).data || []).map((b: { name: string }) => b.name) } catch { /* optional */ }
})

function go() {
  const q: Record<string, string> = { mode: 'deal' }
  if (maxPrice.value) q.max_price = maxPrice.value
  if (city.value) q.city = city.value
  if (tab.value === 'vehicle') {
    if (brand.value) q.brand = brand.value
    if (minYear.value) q.min_year = minYear.value
  } else {
    if (propertyType.value) q.type = propertyType.value
    if (beds.value) q.beds = beds.value
  }
  // Remember the search for the "welcome back" card (best effort, signed-in users only).
  if (auth.isAuthenticated) {
    client.post('/me/search-preferences', { asset_type: tab.value, budget_max: maxPrice.value || null, city: city.value || null, priorities: ['best_deal'] }).catch(() => {})
  }
  router.push({ path: tab.value === 'vehicle' ? '/cars' : tab.value === 'rental' ? '/rentals' : '/properties', query: q })
}
</script>

<style scoped>
.engine { background: linear-gradient(150deg, rgba(10,37,64,.92), rgba(8,48,79,.92)); border: 1px solid rgba(120,190,255,.28); backdrop-filter: blur(16px); box-shadow: 0 28px 70px rgba(2,10,22,.45); }
.chip-ai { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;color:#4fd8f5;background:rgba(18,197,232,.12);border:1px solid rgba(18,197,232,.35) }
.tab { display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:12px;font-size:14px;font-weight:700;color:#cfe0f3;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14) }
.tab--on { color:#04111d;background:linear-gradient(135deg,#12C5E8,#4fe0b0);border-color:transparent }
.field { display:flex;flex-direction:column;gap:4px;min-width:0 }
.field > span { font-size:11px;font-weight:600;color:#a9bdd3 }
.field select { width:100%;padding:10px 12px;border-radius:10px;font-size:13px;background:#ffffff;color:#0b1726;border:1px solid rgba(255,255,255,.2) }
.find-btn { padding:11px 14px;border-radius:12px;font-size:13px;font-weight:800;color:#04111d;background:linear-gradient(135deg,#12C5E8,#4fe0b0);box-shadow:0 8px 22px rgba(18,197,232,.35);white-space:nowrap }
.quick { display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:600;color:#cfe0f3;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);text-decoration:none }
.quick:hover { color:#fff;border-color:rgba(79,216,245,.5) }
</style>
