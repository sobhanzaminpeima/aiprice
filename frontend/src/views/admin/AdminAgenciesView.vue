<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Agency &amp; Dealer <span class="gradient-text">Management</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Manage agencies, dealers and their listings</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)">{{ total }} partners</span>
        <button @click="openAdd" class="px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ Add Agency / Dealer</button>
      </div>
    </div>

    <!-- Add Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)" @click.self="showAddModal=false">
      <div class="w-full max-w-md rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h3 class="text-lg font-bold mb-4" style="color:var(--text-primary)">Add Agency / Dealer</h3>
        <div v-if="addError" class="mb-3 p-3 rounded-xl text-xs" style="background:rgba(var(--danger-rgb),0.1);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ addError }}</div>
        <div class="space-y-3">
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Business / Agency Name *</label>
            <input v-model="addForm.business_name" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Contact Name *</label>
            <input v-model="addForm.name" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Email *</label>
            <input v-model="addForm.email" type="email" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Password *</label>
            <input v-model="addForm.password" type="password" placeholder="Min. 8 characters" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Type</label>
            <select v-model="addForm.role" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="agency">Agency</option>
              <option value="dealer">Dealer</option>
            </select>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Phone</label>
            <input v-model="addForm.phone" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="submitAdd" :disabled="!addForm.name || !addForm.email || addForm.password.length<8 || addSaving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ addSaving?'Creating...':'Create' }}</button>
          <button @click="showAddModal=false" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6 p-4 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <select v-model="filters.role" @change="fetch(1)" class="px-4 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
        <option value="">All Partners</option>
        <option value="agency">Agencies</option>
        <option value="dealer">Dealers</option>
      </select>
      <input v-model="filters.search" @input="debounce" placeholder="Search name or email..." class="px-4 py-2 rounded-xl text-sm flex-1 min-w-48" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-24 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="items.length===0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="text-5xl mb-3">🏢</div>
      <p class="font-semibold" style="color:var(--text-primary)">No agencies or dealers found</p>
      <p class="text-sm mt-1" style="color:var(--text-muted)">Users with agency/dealer role will appear here</p>
    </div>

    <!-- List -->
    <div v-else class="space-y-3">
      <div v-for="u in items" :key="u.id" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full flex items-center justify-center font-black text-lg shrink-0" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">
              {{ u.name?.charAt(0)?.toUpperCase() || 'A' }}
            </div>
            <div>
              <div class="flex items-center gap-2 mb-0.5">
                <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ u.name }}</p>
                <span class="text-xs px-2 py-0.5 rounded-full capitalize font-medium" :style="u.role==='agency'?'background:rgba(var(--accent-violet-rgb),0.1);color:var(--accent-violet)':'background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)'">{{ u.role }}</span>
                <span v-if="u.kyc_verified" class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)">✓ Verified</span>
                <span v-if="!u.is_active" class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(var(--danger-rgb),0.1);color:var(--danger)">Banned</span>
              </div>
              <p class="text-xs" style="color:var(--text-muted)">{{ u.email }} · Joined {{ new Date(u.created_at).toLocaleDateString('en-GB') }}</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">
                Listings: <span style="color:var(--accent-teal)">{{ u.listings_count||0 }}</span>
                <span v-if="u.active_subscription"> · Plan: <span style="color:var(--accent-cyan)">{{ u.active_subscription?.plan?.name }}</span></span>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <button v-if="!u.kyc_verified" @click="verify(u.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)">Verify</button>
            <button @click="openEdit(u)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</button>
            <button @click="toggleActive(u)" class="px-3 py-1.5 text-xs rounded-lg" :style="u.is_active?'background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)':'background:rgba(var(--accent-teal-rgb),0.06);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.15)'">
              {{ u.is_active ? 'Ban' : 'Activate' }}
            </button>
            <button @click="openDetail(u)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-violet-rgb),0.08);color:var(--accent-violet);border:1px solid rgba(var(--accent-violet-rgb),0.2)">View Details</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage>1" class="flex gap-2 justify-center mt-8">
      <button v-for="p in lastPage" :key="p" @click="fetch(p)" class="w-9 h-9 rounded-xl text-xs font-semibold" :style="p===currentPage?'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)':'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">{{ p }}</button>
    </div>

    <!-- Edit Modal -->
    <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.75)" @click.self="editing=null">
      <div class="w-full max-w-md rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Edit Partner</h2>
        <div class="space-y-3">
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Name</label>
            <input v-model="editing.name" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Email</label>
            <input v-model="editing.email" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:var(--text-muted)">Role</label>
            <select v-model="editing.role" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="agency">Agency</option>
              <option value="dealer">Dealer</option>
              <option value="seller">Seller</option>
            </select>
          </div>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="editing.is_active" /> Active</label>
            <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="editing.kyc_verified" /> KYC Verified</label>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="save" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save Changes' }}</button>
          <button @click="editing=null" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.65)" @click.self="detail=null">
      <div class="w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div v-if="detailLoading" class="py-16 text-center" style="color:var(--text-muted)">Loading...</div>
        <template v-else-if="detail">
          <div class="flex items-center justify-between mb-5">
            <div>
              <p class="font-bold text-lg" style="color:var(--text-primary)">{{ detail.name }}</p>
              <p class="text-xs" style="color:var(--text-muted)">{{ detail.email }} · {{ detail.phone || 'no phone' }}</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">Business: {{ detail.profile?.business_name || '—' }}</p>
            </div>
            <button @click="detail=null" class="text-2xl leading-none" style="color:var(--text-muted)">&times;</button>
          </div>
          <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)"><p class="text-xs" style="color:var(--text-muted)">Listings</p><p style="color:var(--text-primary)">{{ detail.listings?.length ?? 0 }}</p></div>
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)"><p class="text-xs" style="color:var(--text-muted)">KYC</p><p style="color:var(--text-primary)">{{ detail.kyc?.status || (detail.kyc_verified ? 'approved' : 'not submitted') }}</p></div>
          </div>
          <div class="mb-5">
            <h4 class="font-semibold text-sm mb-2" style="color:var(--text-primary)">Subscription Plan</h4>
            <div class="p-4 rounded-xl flex items-center justify-between flex-wrap gap-3" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <p class="text-sm" style="color:var(--text-primary)">{{ detail.active_subscription?.plan?.name || 'No active plan' }}</p>
              <div class="flex items-center gap-2">
                <select v-model="planChangeId" class="px-3 py-2 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                  <option value="">Select plan...</option>
                  <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} — {{ p.price_monthly }}/mo</option>
                </select>
                <button @click="changePlan" :disabled="!planChangeId || planSaving" class="px-3 py-2 rounded-lg text-xs font-bold disabled:opacity-40" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ planSaving?'Saving...':'Apply' }}</button>
              </div>
            </div>
          </div>
          <div>
            <h4 class="font-semibold text-sm mb-2" style="color:var(--text-primary)">API Tokens</h4>
            <div v-if="tokensLoading" class="text-xs" style="color:var(--text-muted)">Loading...</div>
            <div v-else-if="tokens.length===0" class="text-xs" style="color:var(--text-muted)">No active tokens.</div>
            <div v-else class="space-y-2">
              <div v-for="t in tokens" :key="t.id" class="flex items-center justify-between p-3 rounded-xl text-xs" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
                <p style="color:var(--text-primary)">{{ t.name }} <span style="color:var(--text-muted)">· {{ new Date(t.created_at).toLocaleDateString('en-GB') }}</span></p>
                <button @click="revokeToken(t.id)" class="px-3 py-1.5 rounded-lg font-medium" style="background:rgba(var(--danger-rgb),0.08);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.2)">Revoke</button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'
const items = ref<any[]>([])
const loading = ref(true)
const saving = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const filters = ref({ role: '', search: '' })
const editing = ref<any>(null)
const showAddModal = ref(false)
const addSaving = ref(false)
const addError = ref('')
const addForm = ref({ name: '', email: '', password: '', role: 'agency', phone: '', business_name: '' })
let debounceTimer: any

function openAdd() {
  addForm.value = { name: '', email: '', password: '', role: 'agency', phone: '', business_name: '' }
  addError.value = ''
  showAddModal.value = true
}

async function submitAdd() {
  addError.value = ''
  addSaving.value = true
  try {
    await client.post('/admin/users', addForm.value)
    showAddModal.value = false
    fetch(1)
  } catch (e: any) {
    addError.value = e?.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(', ')
      : e?.response?.data?.message || 'Failed to create.'
  } finally {
    addSaving.value = false
  }
}

onMounted(() => fetch(1))

async function fetch(page = 1) {
  loading.value = true
  try {
    const r = await client.get('/admin/agencies', { params: { ...filters.value, page } })
    items.value = r.data.data || []
    currentPage.value = r.data.current_page || 1
    lastPage.value = r.data.last_page || 1
    total.value = r.data.total || items.value.length
  } catch { items.value = [] }
  finally { loading.value = false }
}

function debounce() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => fetch(1), 400) }
function openEdit(u: any) { editing.value = { ...u } }

async function save() {
  if (!editing.value) return
  saving.value = true
  try {
    await client.put('/admin/agencies/'+editing.value.id, editing.value)
    editing.value = null; fetch(currentPage.value)
  } catch(e: any) { alert(e?.response?.data?.message||'Failed') }
  finally { saving.value = false }
}

async function verify(id: string) { await client.post('/admin/agencies/'+id+'/verify'); fetch(currentPage.value) }
async function toggleActive(u: any) {
  await client.post('/admin/users/'+u.id+'/toggle-active')
  fetch(currentPage.value)
}

const detail = ref<any>(null)
const detailLoading = ref(false)
const tokens = ref<any[]>([])
const tokensLoading = ref(false)
const plans = ref<any[]>([])
const planChangeId = ref('')
const planSaving = ref(false)

async function openDetail(u: any) {
  detail.value = { id: u.id }
  detailLoading.value = true
  planChangeId.value = ''
  try {
    const [d, p] = await Promise.all([
      client.get(`/admin/users/${u.id}`),
      plans.value.length ? Promise.resolve({ data: plans.value }) : client.get('/admin/subscription-plans'),
    ])
    detail.value = d.data
    plans.value = p.data
    loadTokens(u.id)
  } catch {
    detail.value = null
  } finally {
    detailLoading.value = false
  }
}

async function loadTokens(userId: string) {
  tokensLoading.value = true
  try {
    const r = await client.get(`/admin/users/${userId}/tokens`)
    tokens.value = r.data || []
  } catch { tokens.value = [] }
  finally { tokensLoading.value = false }
}

async function revokeToken(tokenId: string) {
  if (!detail.value) return
  await client.delete(`/admin/users/${detail.value.id}/tokens/${tokenId}`)
  loadTokens(detail.value.id)
}

async function changePlan() {
  if (!detail.value || !planChangeId.value) return
  planSaving.value = true
  try {
    if (detail.value.active_subscription?.id) {
      await client.put(`/admin/subscriptions/${detail.value.active_subscription.id}`, { plan_id: planChangeId.value })
    } else {
      await client.post('/admin/subscriptions', { user_id: detail.value.id, plan_id: planChangeId.value })
    }
    const d = await client.get(`/admin/users/${detail.value.id}`)
    detail.value = d.data
    planChangeId.value = ''
  } finally {
    planSaving.value = false
  }
}
</script>
