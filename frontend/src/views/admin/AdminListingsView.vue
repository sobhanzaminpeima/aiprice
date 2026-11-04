<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Listing <span class="gradient-text">Management</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Approve, reject, edit and manage all listings</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)">{{ total }} listings</span>
        <button @click="openAdd" class="px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ Add Listing</button>
      </div>
    </div>

    <!-- Add Listing Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.75)" @click.self="showAddModal=false">
      <div class="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="font-bold text-lg mb-5" style="color:var(--text-primary)">Add Listing (on behalf of a user)</h2>
        <div v-if="addError" class="mb-3 p-3 rounded-xl text-xs" style="background:rgba(var(--danger-rgb),0.1);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ addError }}</div>
        <div class="space-y-3">
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Owner (search by email) *</label>
            <input v-model="ownerSearch" @input="searchOwners" placeholder="Type email or name..." class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            <div v-if="ownerResults.length" class="mt-1 rounded-xl overflow-hidden" style="border:1px solid var(--border-subtle)">
              <button v-for="o in ownerResults" :key="o.id" @click="selectOwner(o)" class="w-full text-left px-3 py-2 text-xs" style="background:var(--surface-overlay-soft);color:var(--text-primary)">{{ o.name }} — {{ o.email }}</button>
            </div>
            <p v-if="addForm.user_id" class="text-xs mt-1" style="color:var(--accent-teal)">Selected: {{ addForm.owner_label }}</p>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Type *</label>
            <select v-model="addForm.type" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="vehicle">Vehicle</option>
              <option value="property">Property</option>
              <option value="rental">Rental</option>
            </select>
          </div>
          <div v-if="addForm.type==='vehicle'" class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Brand *</label>
              <select v-model="addForm.brand_id" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                <option value="">Select</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Model *</label>
              <select v-model="addForm.model_id" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                <option value="">Select</option>
                <option v-for="m in modelsForAddBrand" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Year *</label>
              <input v-model.number="addForm.year" type="number" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            </div>
          </div>
          <div v-if="addForm.type==='property'">
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Property Type *</label>
            <select v-model="addForm.property_type_id" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="">Select</option>
              <option v-for="pt in propertyTypes" :key="pt.id" :value="pt.id">{{ pt.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Title *</label>
            <input v-model="addForm.title" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Price *</label>
              <input v-model.number="addForm.price" type="number" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            </div>
            <div>
              <label class="text-xs mb-1 block" style="color:var(--text-muted)">Currency</label>
              <select v-model="addForm.currency" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                <option>GBP</option><option>USD</option><option>EUR</option><option>TRY</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">City</label>
            <input v-model="addForm.city" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Description</label>
            <textarea v-model="addForm.description" rows="3" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Status</label>
            <select v-model="addForm.status" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="active">Active (publish immediately)</option>
              <option value="pending">Pending Review</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="submitAddListing" :disabled="!canSubmitListing || addSaving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ addSaving?'Creating...':'Create Listing' }}</button>
          <button @click="showAddModal=false" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6 p-4 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <select v-model="filters.status" @change="fetchListings(1)" class="px-4 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
        <option value="">All Status</option>
        <option value="pending">Pending Review</option>
        <option value="active">Active</option>
        <option value="rejected">Rejected</option>
        <option value="sold">Sold</option>
        <option value="expired">Expired</option>
      </select>
      <select v-model="filters.type" @change="fetchListings(1)" class="px-4 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
        <option value="">All Types</option>
        <option value="vehicle">Vehicles</option>
        <option value="property">Properties</option>
        <option value="rental">Rentals</option>
      </select>
      <input v-model="filters.search" @input="debounce" placeholder="Search title, number, city..." class="px-4 py-2 rounded-xl text-sm flex-1 min-w-48" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 6" :key="i" class="h-20 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="listings.length===0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:rgba(var(--accent-teal-rgb),0.08);border:1px solid rgba(var(--accent-teal-rgb),0.15)">
        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
      </div>
      <p class="font-semibold" style="color:var(--text-primary)">No listings found</p>
    </div>

    <!-- List -->
    <div v-else class="space-y-3">
      <div v-for="l in listings" :key="l.id" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0" style="background:var(--surface-overlay)">
              <img v-if="l.thumbnail" :src="l.thumbnail" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-xl"><span v-if="l.type==='vehicle'" style="display:inline-flex;align-items:center"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="teal" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/></svg></span><span v-else-if="l.type==='property'" style="display:inline-flex;align-items:center"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="violet" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/></svg></span><span v-else style="display:inline-flex;align-items:center"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" data-tone="cyan" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg></span></div>
            </div>
            <div>
              <p class="font-semibold text-sm mb-0.5" style="color:var(--text-primary)">{{ l.title }}</p>
              <p class="text-xs" style="color:var(--text-muted)">{{ l.user?.name }} · {{ l.city || '-' }} · #{{ l.listing_number }}</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ l.type }} · {{ l.created_at ? new Date(l.created_at).toLocaleDateString('en-GB') : '' }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-bold text-sm" style="color:var(--accent-teal)">{{ fmt(l.price, l.currency) }}</span>
            <div v-if="l.price_battery_percent != null" class="flex items-center gap-1">
              <div class="w-10 h-2 rounded-full overflow-hidden" style="background:var(--border-subtle)">
                <div class="h-full rounded-full" :style="'width:'+l.price_battery_percent+'%;background:'+(l.price_battery_percent>=80?'var(--accent-teal)':l.price_battery_percent>=50?'var(--warning)':'var(--danger)')"></div>
              </div>
              <span class="text-xs font-bold" :style="'color:'+(l.price_battery_percent>=80?'var(--accent-teal)':l.price_battery_percent>=50?'var(--warning)':'var(--danger)')">{{ l.price_battery_percent }}%</span>
            </div>
            <span class="text-xs px-2 py-1 rounded-full capitalize font-medium" :style="statusStyle(l.status)">{{ l.status }}</span>
            <span v-if="l.is_featured" class="text-xs px-2 py-1 rounded-full font-medium flex items-center gap-1" style="background:rgba(var(--warning-rgb),0.1);color:var(--warning)"><svg width="10" height="10" fill="currentColor" data-tone="warning" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg> Featured</span>
            <span v-if="l.is_premium" class="text-xs px-2 py-1 rounded-full font-medium" style="background:rgba(var(--accent-violet-rgb),0.1);color:var(--accent-violet)">Premium</span>
            <button v-if="l.status==='pending'" @click="approve(l.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)">Approve</button>
            <button v-if="l.status==='pending'" @click="reject(l.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.08);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.2)">Reject</button>
            <button @click="feature(l.id)" class="px-3 py-1.5 text-xs rounded-lg flex items-center gap-1" :style="l.is_featured?'background:rgba(var(--warning-rgb),0.1);color:var(--warning);border:1px solid rgba(var(--warning-rgb),0.2)':'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
              <svg width="10" height="10" :fill="l.is_featured?'var(--warning)':'none'" viewBox="0 0 24 24" :stroke="l.is_featured?'var(--warning)':'var(--text-muted)'" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
              {{ l.is_featured ? 'Unfeature' : 'Feature' }}
            </button>
            <RouterLink :to="`/admin/listings/${l.id}/edit`" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</RouterLink>
            <button @click="reanalyze(l.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-violet-rgb),0.08);color:var(--accent-violet);border:1px solid rgba(var(--accent-violet-rgb),0.2)">AI</button>
            <button @click="del(l.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage>1" class="flex gap-2 justify-center mt-8">
      <button v-for="p in lastPage" :key="p" @click="fetchListings(p)" class="w-9 h-9 rounded-xl text-xs font-semibold" :style="p===currentPage?'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)':'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">{{ p }}</button>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import client from '@/api/client'
const listings = ref<any[]>([])
const loading = ref(true)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const filters = ref({ status: '', type: '', search: '' })
let debounceTimer: any

onMounted(() => fetchListings(1))

async function fetchListings(page = 1) {
  loading.value = true
  try {
    const r = await client.get('/admin/listings', { params: { ...filters.value, page } })
    listings.value = r.data.data || []
    currentPage.value = r.data.current_page || 1
    lastPage.value = r.data.last_page || 1
    total.value = r.data.total || listings.value.length
  } catch { listings.value = [] }
  finally { loading.value = false }
}

function debounce() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchListings(1), 400)
}

// Add Listing (on behalf of a user)
const showAddModal = ref(false)
const addSaving = ref(false)
const addError = ref('')
const ownerSearch = ref('')
const ownerResults = ref<any[]>([])
const brands = ref<Array<{ id: string; name: string; models: Array<{ id: string; name: string }> }>>([])
const propertyTypes = ref<Array<{ id: string; name: string }>>([])
const addForm = ref<any>({ user_id: '', owner_label: '', type: 'vehicle', title: '', description: '', price: '', currency: 'GBP', city: '', status: 'active', brand_id: '', model_id: '', year: '', property_type_id: '' })
let ownerSearchTimer: any

async function openAdd() {
  addForm.value = { user_id: '', owner_label: '', type: 'vehicle', title: '', description: '', price: '', currency: 'GBP', city: '', status: 'active', brand_id: '', model_id: '', year: '', property_type_id: '' }
  addError.value = ''
  ownerSearch.value = ''
  ownerResults.value = []
  showAddModal.value = true
  if (!brands.value.length) {
    const [b, p] = await Promise.all([client.get('/vehicle-brands').catch(() => ({ data: [] })), client.get('/property-types').catch(() => ({ data: [] }))])
    brands.value = b.data || []
    propertyTypes.value = p.data || []
  }
}

const modelsForAddBrand = computed(() => brands.value.find(b => b.id === addForm.value.brand_id)?.models ?? [])

function searchOwners() {
  clearTimeout(ownerSearchTimer)
  if (ownerSearch.value.trim().length < 2) { ownerResults.value = []; return }
  ownerSearchTimer = setTimeout(async () => {
    const r = await client.get('/admin/users', { params: { search: ownerSearch.value, page: 1 } }).catch(() => ({ data: { data: [] } }))
    ownerResults.value = (r.data.data || []).slice(0, 6)
  }, 350)
}

function selectOwner(o: any) {
  addForm.value.user_id = o.id
  addForm.value.owner_label = `${o.name} (${o.email})`
  ownerSearch.value = ''
  ownerResults.value = []
}

const canSubmitListing = computed(() => {
  const f = addForm.value
  if (!f.user_id || !f.title || !f.price) return false
  if (f.type === 'vehicle') return !!(f.brand_id && f.model_id && f.year)
  if (f.type === 'property') return !!f.property_type_id
  return true
})

async function submitAddListing() {
  addError.value = ''
  addSaving.value = true
  try {
    await client.post('/admin/listings', addForm.value)
    showAddModal.value = false
    fetchListings(1)
  } catch (e: any) {
    addError.value = e?.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(', ')
      : e?.response?.data?.message || 'Failed to create listing.'
  } finally {
    addSaving.value = false
  }
}

async function approve(id: string) { await client.post('/admin/listings/'+id+'/approve'); fetchListings(currentPage.value) }
async function reject(id: string) {
  const reason = prompt('Rejection reason (required):')
  if (!reason) return
  await client.post('/admin/listings/'+id+'/reject', { reason })
  fetchListings(currentPage.value)
}
async function feature(id: string) { await client.post('/admin/listings/'+id+'/feature'); fetchListings(currentPage.value) }
async function reanalyze(id: string) { await client.post('/admin/listings/'+id+'/reanalyze'); fetchListings(currentPage.value) }
async function del(id: string) { if (!confirm('Delete this listing permanently?')) return; await client.delete('/admin/listings/'+id); fetchListings(currentPage.value) }

function statusStyle(s: string) {
  const m: Record<string,string> = { active:'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)', pending:'background:rgba(var(--warning-rgb),0.1);color:var(--warning);border:1px solid rgba(var(--warning-rgb),0.2)', rejected:'background:rgba(var(--danger-rgb),0.1);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.2)', sold:'background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)', expired:'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-strong)' }
  return m[s] || m.expired
}
function fmt(v: number, c = 'GBP') { return new Intl.NumberFormat('en-GB', { style:'currency', currency:c||'GBP', maximumFractionDigits:0 }).format(v) }
</script>
