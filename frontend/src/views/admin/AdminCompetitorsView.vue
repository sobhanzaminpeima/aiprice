<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Competitor <span class="gradient-text">Intelligence</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Market data sources — used only for AI price analysis, never shown as listings</p>
      </div>
      <div class="flex gap-3">
        <button @click="importAll" :disabled="importing"
          class="px-4 py-2.5 text-sm font-semibold rounded-xl disabled:opacity-50 transition-all"
          style="background:rgba(var(--accent-violet-rgb),0.1);color:var(--accent-violet);border:1px solid rgba(var(--accent-violet-rgb),0.25)">
          {{ importing ? 'Importing...' : 'Import All' }}
        </button>
        <button @click="showAdd = true"
          class="px-5 py-2.5 text-sm font-semibold rounded-xl"
          style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
          + Add Source
        </button>
      </div>
    </div>

    <!-- Import result -->
    <div v-if="importResult" class="mb-6 rounded-2xl p-4"
      style="background:rgba(var(--accent-teal-rgb),0.06);border:1px solid rgba(var(--accent-teal-rgb),0.2)">
      <p class="font-semibold mb-2" style="color:var(--accent-teal)">Import Complete</p>
      <div v-for="r in importResult" :key="r.source" class="text-sm" style="color:var(--text-secondary)">
        {{ r.source }}: +{{ r.imported }} new, {{ r.updated }} updated
        <span v-if="r.errors" style="color:var(--danger)"> ({{ r.errors }} errors)</span>
      </div>
    </div>

    <!-- Sources -->
    <div class="space-y-4 mb-8">
      <div v-for="s in sources" :key="s.id" class="rounded-2xl p-5"
        style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <h3 class="font-semibold text-lg" style="color:var(--text-primary)">{{ s.name }}</h3>
              <span class="text-xs px-2 py-0.5 rounded-full capitalize font-medium"
                :style="s.is_active ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)' : 'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'">
                {{ s.is_active ? 'Active' : 'Paused' }}
              </span>
              <span class="text-xs px-2 py-0.5 rounded-full capitalize"
                style="background:var(--border-subtle);color:var(--text-muted)">
                {{ s.type }}
              </span>
            </div>
            <a :href="s.url" target="_blank" class="text-xs" style="color:var(--accent-cyan)">{{ s.url }}</a>
            <div class="flex items-center gap-6 mt-3">
              <div class="text-center">
                <p class="text-2xl font-black" style="color:var(--text-primary)">{{ (stats[s.id]?.total_listings || 0).toLocaleString() }}</p>
                <p class="text-xs" style="color:var(--text-muted)">Listings</p>
              </div>
              <div class="text-center">
                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ stats[s.id]?.avg_price ? formatPrice(stats[s.id].avg_price, s.type === 'vehicle' ? 'TRY' : 'GBP') : '-' }}</p>
                <p class="text-xs" style="color:var(--text-muted)">Avg Price</p>
              </div>
              <div class="text-center">
                <p class="text-xs" style="color:var(--text-secondary)">{{ s.last_scraped_at ? formatDate(s.last_scraped_at) : 'Never' }}</p>
                <p class="text-xs" style="color:var(--text-muted)">Last Import</p>
              </div>
              <div class="text-center">
                <p class="text-xs capitalize" style="color:var(--text-secondary)">{{ s.scrape_interval || 'daily' }}</p>
                <p class="text-xs" style="color:var(--text-muted)">Schedule</p>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2 ml-6">
            <button @click="importSource(s)" :disabled="importingId === s.id"
              class="px-4 py-2 text-xs rounded-xl font-medium disabled:opacity-50 transition-all"
              style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">
              {{ importingId === s.id ? 'Importing...' : 'Import Now' }}
            </button>
            <button @click="toggleSource(s)"
              class="px-4 py-2 text-xs rounded-xl font-medium transition-all"
              style="background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)">
              {{ s.is_active ? 'Pause' : 'Activate' }}
            </button>
            <button @click="viewListings(s)"
              class="px-4 py-2 text-xs rounded-xl font-medium transition-all"
              style="background:rgba(var(--accent-violet-rgb),0.08);color:var(--accent-violet);border:1px solid rgba(var(--accent-violet-rgb),0.2)">
              View Data
            </button>
          </div>
        </div>
      </div>

      <div v-if="sources.length === 0" class="text-center py-16 rounded-2xl"
        style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="text-5xl mb-3">C</div>
        <p class="font-semibold" style="color:var(--text-primary)">No competitor sources configured</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Add kktcarabam, hangiev, noyanlarinvest, etc.</p>
      </div>
    </div>

    <!-- Data Preview Table -->
    <div v-if="selectedSource" class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">{{ selectedSource.name }} — Imported Data</h2>
        <span class="text-xs px-3 py-1 rounded-full"
          style="background:rgba(var(--warning-rgb),0.1);color:var(--warning);border:1px solid rgba(var(--warning-rgb),0.2)">
          AI Analysis Only — Not Public
        </span>
      </div>
      <div v-if="loadingListings" class="space-y-2">
        <div v-for="i in 5" :key="i" class="h-12 rounded-xl animate-pulse" style="background:var(--surface-overlay)"></div>
      </div>
      <table v-else class="w-full">
        <thead>
          <tr class="text-xs" style="color:var(--text-muted);border-bottom:1px solid var(--border-subtle)">
            <th class="text-left pb-3 font-medium">Title</th>
            <th class="text-left pb-3 font-medium">Price</th>
            <th class="text-left pb-3 font-medium">City</th>
            <th class="text-left pb-3 font-medium">Type</th>
            <th class="text-left pb-3 font-medium">Imported</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in competitorListings" :key="l.id" class="border-b text-xs"
            style="border-color:var(--surface-overlay)">
            <td class="py-3" style="color:var(--text-primary)">{{ l.title }}</td>
            <td class="py-3 font-medium" style="color:var(--accent-teal)">{{ l.price ? formatPrice(l.price, l.currency) : '-' }}</td>
            <td class="py-3" style="color:var(--text-muted)">{{ l.city || '-' }}</td>
            <td class="py-3">
              <span class="px-2 py-0.5 rounded-full capitalize"
                style="background:var(--border-subtle);color:var(--text-muted)">{{ l.type }}</span>
            </td>
            <td class="py-3" style="color:var(--text-muted)">{{ formatDate(l.created_at) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="competitorPagination.last_page > 1" class="flex gap-2 mt-4">
        <button v-for="p in competitorPagination.last_page" :key="p" @click="loadListingsPage(p)"
          class="w-8 h-8 rounded-lg text-xs font-semibold"
          :style="p === competitorPagination.current_page ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)' : 'background:var(--border-subtle);color:var(--text-muted)'">
          {{ p }}
        </button>
      </div>
    </div>

    <!-- Add Source Modal -->
    <div v-if="showAdd" class="fixed inset-0 flex items-center justify-center z-50"
      style="background:rgba(0,0,0,0.7)" @click.self="showAdd = false">
      <div class="rounded-2xl p-6 w-full max-w-md" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.2)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Add Competitor Source</h2>
        <form @submit.prevent="addSource" class="space-y-4">
          <input v-model="newSource.name" required placeholder="Display name (e.g. KKT Carabam)"
            class="w-full px-4 py-3 rounded-xl text-sm outline-none"
            style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <input v-model="newSource.url" required type="url" placeholder="https://..."
            class="w-full px-4 py-3 rounded-xl text-sm outline-none"
            style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <select v-model="newSource.type"
            class="w-full px-4 py-3 rounded-xl text-sm"
            style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
            <option value="vehicle">Vehicle</option>
            <option value="property">Property</option>
            <option value="rental">Rental</option>
          </select>
          <select v-model="newSource.scrape_interval"
            class="w-full px-4 py-3 rounded-xl text-sm"
            style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
            <option value="hourly">Every Hour</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
          </select>
          <div class="flex gap-3">
            <button type="submit" class="flex-1 py-3 font-bold text-sm rounded-xl"
              style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
              Add Source
            </button>
            <button type="button" @click="showAdd = false" class="flex-1 py-3 text-sm rounded-xl"
              style="border:1px solid var(--border-strong);color:var(--text-secondary)">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'

const sources = ref<any[]>([])
const stats = ref<Record<string, any>>({})
const showAdd = ref(false)
const importing = ref(false)
const importingId = ref<string | null>(null)
const importResult = ref<any[] | null>(null)
const selectedSource = ref<any>(null)
const competitorListings = ref<any[]>([])
const competitorPagination = ref({ current_page: 1, last_page: 1 })
const loadingListings = ref(false)
const newSource = ref({ name: '', url: '', type: 'vehicle', scrape_interval: 'daily' })

onMounted(fetchSources)

async function fetchSources() {
  try {
    const r = await client.get('/admin/competitor-sources')
    sources.value = r.data || []
    for (const s of sources.value) {
      try {
        const sr = await client.get(`/admin/competitor-sources/${s.id}/stats`)
        stats.value[s.id] = sr.data
      } catch {}
    }
  } catch { sources.value = [] }
}

async function addSource() {
  await client.post('/admin/competitor-sources', newSource.value)
  showAdd.value = false
  newSource.value = { name: '', url: '', type: 'vehicle', scrape_interval: 'daily' }
  fetchSources()
}

async function toggleSource(s: any) {
  await client.put(`/admin/competitor-sources/${s.id}`, { is_active: !s.is_active })
  fetchSources()
}

async function importSource(s: any) {
  importingId.value = s.id
  importResult.value = null
  try {
    const r = await client.post(`/admin/competitor-sources/${s.id}/import`)
    importResult.value = [r.data]
  } finally {
    importingId.value = null
    fetchSources()
  }
}

async function importAll() {
  importing.value = true
  importResult.value = null
  try {
    const r = await client.post('/admin/competitor-sources/import-all')
    importResult.value = r.data
  } finally {
    importing.value = false
    fetchSources()
  }
}

async function viewListings(s: any) {
  selectedSource.value = s
  loadListingsPage(1)
}

async function loadListingsPage(page: number) {
  loadingListings.value = true
  try {
    const r = await client.get('/admin/competitor-listings', {
      params: { source_id: selectedSource.value?.id, page }
    })
    competitorListings.value = r.data.data || []
    competitorPagination.value = r.data
  } finally {
    loadingListings.value = false
  }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('en-GB') : '-' }
function formatPrice(v: number, cur = 'GBP') {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur || 'GBP', maximumFractionDigits: 0 }).format(v)
}
</script>
