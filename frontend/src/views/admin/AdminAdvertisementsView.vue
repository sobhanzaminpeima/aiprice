<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Advertisement <span class="gradient-text">Management</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Manage Top 10 sponsored listings and banner ads</p>
      </div>
      <button @click="showAdd=true" class="px-5 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ New Ad</button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-8">
      <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" style="color:var(--accent-teal)">{{ ads.filter(a=>a.is_active).length }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Active Ads</p>
      </div>
      <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" style="color:var(--accent-cyan)">{{ ads.filter(a=>a.slot==='top10').length }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Top 10 Slots</p>
      </div>
      <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" style="color:var(--warning)">{{ ads.reduce((s,a)=>s+(a.impression_count||0),0).toLocaleString() }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Total Impressions</p>
      </div>
      <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" style="color:var(--accent-violet)">{{ ads.reduce((s,a)=>s+(a.click_count||0),0).toLocaleString() }}</p>
        <p class="text-xs" style="color:var(--text-muted)">Total Clicks</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6">
      <button v-for="t in slotTabs" :key="t.id" @click="activeTab=t.id" class="px-4 py-2 rounded-xl text-sm font-semibold"
        :style="activeTab===t.id?'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)':'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
        {{ t.label }} ({{ ads.filter(a=>t.id==='all'||a.slot===t.id).length }})
      </button>
    </div>

    <!-- Ads list -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="h-20 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>
    <div v-else-if="filteredAds.length===0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p style="color:var(--text-muted)">No advertisements in this category.</p>
    </div>
    <div v-else class="space-y-3">
      <div v-for="ad in filteredAds" :key="ad.id" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(var(--warning-rgb),0.1);border:1px solid rgba(var(--warning-rgb),0.2)">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="warning" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            </div>
            <div>
              <div class="flex items-center gap-2 mb-0.5">
                <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ ad.name }}</p>
                <span class="text-xs px-2 py-0.5 rounded-full capitalize" style="background:rgba(var(--warning-rgb),0.1);color:var(--warning)">{{ ad.slot }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full capitalize" style="background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)">{{ ad.type }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :style="ad.is_active?'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)':'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'">{{ ad.is_active?'Active':'Paused' }}</span>
              </div>
              <p class="text-xs" style="color:var(--text-muted)">
                Impressions: {{ (ad.impression_count||0).toLocaleString() }} · Clicks: {{ (ad.click_count||0).toLocaleString() }}
                <span v-if="ad.starts_at"> · {{ new Date(ad.starts_at).toLocaleDateString('en-GB') }} → {{ ad.ends_at ? new Date(ad.ends_at).toLocaleDateString('en-GB') : 'No end' }}</span>
              </p>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="toggleActive(ad)" class="px-3 py-1.5 text-xs rounded-lg" :style="ad.is_active?'background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)':'background:rgba(var(--accent-teal-rgb),0.06);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.15)'">
              {{ ad.is_active ? 'Pause' : 'Activate' }}
            </button>
            <button @click="openEdit(ad)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</button>
            <button @click="del(ad.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAdd||editing" class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.75)" @click.self="showAdd=false;editing=null">
      <div class="w-full max-w-lg rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">{{ editing ? 'Edit Ad' : 'New Advertisement' }}</h2>
        <div class="space-y-3">
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Name (internal)</label>
            <input v-model="form.name" placeholder="e.g. Toyota Camry Top10 Promo" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Slot</label>
              <select v-model="form.slot" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                <option value="top10">Top 10 (Homepage)</option>
                <option value="banner">Banner</option>
                <option value="sidebar">Sidebar</option>
                <option value="homepage">Homepage Featured</option>
              </select>
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Type</label>
              <select v-model="form.type" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                <option value="vehicle">Vehicle</option>
                <option value="property">Property</option>
                <option value="both">Both</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Listing ID (to link to a specific listing)</label>
            <input v-model="form.listing_id" placeholder="UUID of the listing (optional)" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">External Link (if no listing)</label>
            <input v-model="form.link" placeholder="https://..." class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Start Date</label>
              <input v-model="form.starts_at" type="date" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">End Date</label>
              <input v-model="form.ends_at" type="date" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            </div>
          </div>
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)">
            <input type="checkbox" v-model="form.is_active" /> Active immediately
          </label>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="save" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save Ad' }}</button>
          <button @click="showAdd=false;editing=null" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'

interface Ad {
  id: string
  name: string
  slot: string
  type: string
  is_active: boolean
  impression_count: number
  click_count: number
  starts_at: string | null
  ends_at: string | null
  listing_id: string | null
  link: string | null
}

const ads = ref<Ad[]>([])
const loading = ref(true)
const saving = ref(false)
const showAdd = ref(false)
const editing = ref<Ad | null>(null)
const activeTab = ref('all')
const slotTabs = [
  { id: 'all', label: 'All' },
  { id: 'top10', label: 'Top 10' },
  { id: 'banner', label: 'Banner' },
  { id: 'sidebar', label: 'Sidebar' },
  { id: 'homepage', label: 'Homepage' },
]

const defaultForm = () => ({ name: '', slot: 'top10', type: 'vehicle', link: '', listing_id: '', starts_at: '', ends_at: '', is_active: true })
const form = ref(defaultForm())

const filteredAds = computed(() =>
  activeTab.value === 'all' ? ads.value : ads.value.filter(a => a.slot === activeTab.value)
)

onMounted(fetchAds)

async function fetchAds() {
  loading.value = true
  try { const r = await client.get('/admin/advertisements'); ads.value = r.data || [] }
  catch { ads.value = [] }
  finally { loading.value = false }
}

function openEdit(ad: Ad) {
  editing.value = ad
  form.value = { name: ad.name, slot: ad.slot, type: ad.type, link: ad.link||'', listing_id: ad.listing_id||'', starts_at: ad.starts_at?.slice(0,10)||'', ends_at: ad.ends_at?.slice(0,10)||'', is_active: ad.is_active }
}

async function save() {
  if (!form.value.name) return
  saving.value = true
  try {
    const payload = { ...form.value, listing_id: form.value.listing_id||undefined, link: form.value.link||undefined, starts_at: form.value.starts_at||undefined, ends_at: form.value.ends_at||undefined }
    if (editing.value) await client.put('/admin/advertisements/'+editing.value.id, payload)
    else await client.post('/admin/advertisements', payload)
    showAdd.value = false; editing.value = null; form.value = defaultForm()
    await fetchAds()
  } catch(e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    alert(err?.response?.data?.message || 'Failed')
  } finally { saving.value = false }
}

async function toggleActive(ad: Ad) {
  await client.put('/admin/advertisements/'+ad.id, { is_active: !ad.is_active })
  await fetchAds()
}

async function del(id: string) {
  if (!confirm('Delete this ad?')) return
  await client.delete('/admin/advertisements/'+id)
  await fetchAds()
}
</script>