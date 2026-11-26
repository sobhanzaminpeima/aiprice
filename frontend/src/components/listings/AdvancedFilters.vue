<template>
  <div class="rounded-2xl mb-6" style="background:var(--bg-card);border:1px solid var(--border-subtle)">
    <button type="button" class="w-full flex items-center justify-between px-4 py-3 text-sm font-bold" :aria-expanded="open" @click="open = !open" style="color:var(--text-primary)">
      <span class="flex items-center gap-2">⚙ {{ $t('More filters') }}<span v-if="activeCount" class="count">{{ activeCount }}</span></span>
      <span aria-hidden="true" style="color:var(--text-muted)">{{ open ? '−' : '+' }}</span>
    </button>
    <div v-show="open" class="px-4 pb-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
      <!-- Price -->
      <label class="f"><span>{{ $t('Min price') }}</span><input v-model="model.min_price" type="number" min="0" inputmode="numeric" placeholder="£" @change="apply" /></label>
      <label class="f"><span>{{ $t('Max price') }}</span><input v-model="model.max_price" type="number" min="0" inputmode="numeric" placeholder="£" @change="apply" /></label>

      <!-- Vehicles -->
      <template v-if="kind === 'vehicle'">
        <label class="f"><span>{{ $t('Make') }}</span>
          <select v-model="model.brand" @change="model.model = ''; apply()"><option value="">{{ $t('Any') }}</option><option v-for="b in brands" :key="b.name" :value="b.name">{{ b.name }}</option></select>
        </label>
        <label class="f"><span>{{ $t('Model') }}</span>
          <select v-model="model.model" :disabled="!models.length" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="m in models" :key="m" :value="m">{{ m }}</option></select>
        </label>
        <label class="f"><span>{{ $t('Body Type') }}</span>
          <select v-model="model.body" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="b in bodies" :key="b" :value="b">{{ $t(b) }}</option></select>
        </label>
        <label class="f"><span>{{ $t('Year from') }}</span>
          <select v-model="model.min_year" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option></select>
        </label>
        <label class="f"><span>{{ $t('Year to') }}</span>
          <select v-model="model.max_year" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option></select>
        </label>
        <label class="f"><span>{{ $t('Max mileage') }}</span>
          <select v-model="model.max_mileage" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="k in [25000, 50000, 100000, 150000, 200000]" :key="k" :value="String(k)">≤ {{ k.toLocaleString('en-GB') }} km</option></select>
        </label>
      </template>

      <!-- Properties and rentals -->
      <template v-else>
        <label class="f"><span>{{ $t('Bedrooms') }}</span>
          <select v-model="model.beds" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="n in [0, 1, 2, 3, 4, 5]" :key="n" :value="String(n)">{{ n === 0 ? $t('Studio') + '+' : n + '+' }}</option></select>
        </label>
        <label class="f"><span>{{ $t('Bathrooms') }}</span>
          <select v-model="model.baths" @change="apply"><option value="">{{ $t('Any') }}</option><option v-for="n in [1, 2, 3, 4]" :key="n" :value="String(n)">{{ n }}+</option></select>
        </label>
        <label class="f"><span>{{ $t('Min area (m²)') }}</span><input v-model="model.min_area" type="number" min="0" inputmode="numeric" @change="apply" /></label>
        <label class="f"><span>{{ $t('Max area (m²)') }}</span><input v-model="model.max_area" type="number" min="0" inputmode="numeric" @change="apply" /></label>
        <label class="f"><span>{{ $t('Furnishing') }}</span>
          <select v-model="model.furnishing" @change="apply"><option value="">{{ $t('Any') }}</option><option value="furnished">{{ $t('Furnished') }}</option><option value="semi-furnished">{{ $t('Semi-furnished') }}</option><option value="unfurnished">{{ $t('Unfurnished') }}</option></select>
        </label>
      </template>

      <!-- AI and freshness -->
      <label class="f"><span>{{ $t('AI price check') }}</span>
        <select v-model="model.ai" @change="apply">
          <option value="">{{ $t('Any') }}</option>
          <option value="checked">{{ $t('Price checked by AI') }}</option>
          <option value="fair">{{ $t('Fair price') }}</option>
          <option value="good">{{ $t('Good deal') }}</option>
          <option value="below">{{ $t('Below market range') }}</option>
        </select>
      </label>
      <label class="f"><span>{{ $t('Posted') }}</span>
        <select v-model="model.posted" @change="apply"><option value="">{{ $t('Any time') }}</option><option value="1">{{ $t('Last 24 hours') }}</option><option value="3">{{ $t('Last 3 days') }}</option><option value="7">{{ $t('Last 7 days') }}</option><option value="30">{{ $t('Last 30 days') }}</option></select>
      </label>

      <div class="col-span-2 md:col-span-4 lg:col-span-6 flex flex-wrap items-center gap-4 pt-1 text-sm" style="color:var(--text-secondary)">
        <label class="flex items-center gap-2"><input type="checkbox" v-model="model.photos" true-value="1" false-value="" @change="apply" /> {{ $t('With photos only') }}</label>
        <template v-if="kind === 'property'">
          <label class="flex items-center gap-2"><input type="checkbox" v-model="model.pool" true-value="1" false-value="" @change="apply" /> {{ $t('Pool') }}</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="model.garden" true-value="1" false-value="" @change="apply" /> {{ $t('Garden') }}</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="model.parking" true-value="1" false-value="" @change="apply" /> {{ $t('Parking') }}</label>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import client from '@/api/client'
import { ADVANCED_KEYS } from '@/components/listings/advancedFilters'

const props = defineProps<{ kind: 'vehicle' | 'property' | 'rental' }>()
const model = defineModel<Record<string, string>>({ required: true })
const emit = defineEmits<{ apply: [] }>()

const keys = computed(() => ADVANCED_KEYS[props.kind])
const activeCount = computed(() => keys.value.filter((k) => model.value[k]).length)
const open = ref(false)
onMounted(() => { if (activeCount.value) open.value = true })

const bodies = ['Sedan', 'SUV', 'Hatchback', 'Crossover', 'Pickup', 'Coupe', 'Convertible', 'Van']
const thisYear = new Date().getFullYear()
const years = Array.from({ length: 30 }, (_, i) => thisYear + 1 - i)
const brands = ref<{ name: string; models?: { name: string }[] }[]>([])
const models = computed(() => (brands.value.find((b) => b.name === model.value.brand)?.models ?? []).map((m) => m.name))
onMounted(async () => {
  if (props.kind !== 'vehicle') return
  try { brands.value = (await client.get('/vehicle-brands')).data || [] } catch { /* optional */ }
})
function apply() { emit('apply') }
</script>

<style scoped>
.f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.f > span { font-size: 11px; font-weight: 600; color: var(--text-muted); }
.f input, .f select { width: 100%; padding: 9px 10px; border-radius: 10px; font-size: 13px; background: var(--surface-overlay); color: var(--text-primary); border: 1px solid var(--border-strong); }
.count { display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:999px;font-size:11px;color:#fff;background:var(--accent-cyan) }
</style>
