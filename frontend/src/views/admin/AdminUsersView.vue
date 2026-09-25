<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">User <span class="gradient-text">Management</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Manage users, KYC verification, and account status</p>
      </div>
      <button @click="openAdd" class="px-5 py-2.5 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">+ Add User</button>
    </div>

    <!-- Add User Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)" @click.self="showAddModal = false">
      <div class="w-full max-w-md rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h3 class="text-lg font-bold mb-4" style="color:var(--text-primary)">Add New User</h3>
        <div v-if="addError" class="mb-3 p-3 rounded-xl text-xs" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ addError }}</div>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium mb-1 block" style="color:var(--text-muted)">Full Name *</label>
            <input v-model="addForm.name" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block" style="color:var(--text-muted)">Email *</label>
            <input v-model="addForm.email" type="email" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block" style="color:var(--text-muted)">Password *</label>
            <input v-model="addForm.password" type="password" placeholder="Min. 8 characters" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block" style="color:var(--text-muted)">Role *</label>
            <select v-model="addForm.role" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option v-for="r in roles" :key="r" :value="r" class="capitalize">{{ r }}</option>
            </select>
          </div>
          <div v-if="addForm.role === 'agency' || addForm.role === 'dealer'">
            <label class="text-xs font-medium mb-1 block" style="color:var(--text-muted)">Business / Agency Name</label>
            <input v-model="addForm.business_name" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <div>
            <label class="text-xs font-medium mb-1 block" style="color:var(--text-muted)">Phone</label>
            <input v-model="addForm.phone" class="w-full px-3 py-2 rounded-lg text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="showAddModal = false" class="px-4 py-2 rounded-xl text-sm font-semibold" style="background:var(--border-subtle);color:var(--text-secondary)">Cancel</button>
          <button @click="submitAdd" :disabled="!addForm.name || !addForm.email || addForm.password.length < 8 || addSaving"
            class="px-4 py-2 rounded-xl text-sm font-bold disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
            {{ addSaving ? 'Creating...' : 'Create User' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6 p-4 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <input v-model="search" @input="debouncedFetch" placeholder="Search by name or email..."
        class="px-4 py-2 rounded-xl text-sm flex-1 min-w-56" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
      <select v-model="roleFilter" @change="fetchUsers"
        class="px-4 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
        <option value="">All Roles</option>
        <option v-for="r in roles" :key="r" :value="r" class="capitalize">{{ r }}</option>
      </select>
      <select v-model="kycFilter" @change="fetchUsers"
        class="px-4 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
        <option value="">KYC: All</option>
        <option value="1">KYC Verified</option>
        <option value="0">KYC Pending</option>
      </select>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 6" :key="i" class="rounded-2xl h-16 animate-pulse" style="background:var(--bg-surface)"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="users.length === 0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="text-5xl mb-3">👥</div>
      <p class="font-semibold" style="color:var(--text-primary)">No users found</p>
    </div>

    <!-- Users list -->
    <div v-else class="space-y-3">
      <div v-for="u in users" :key="u.id" class="rounded-2xl p-4 flex items-center justify-between flex-wrap gap-4"
        style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <!-- Avatar + info -->
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0"
            style="background:linear-gradient(135deg,rgba(111,255,210,0.2),rgba(86,216,255,0.2));color:var(--accent-teal)">
            {{ u.name?.charAt(0)?.toUpperCase() || '?' }}
          </div>
          <div>
            <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ u.name }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ u.email }}</p>
          </div>
        </div>

        <!-- Badges + Actions -->
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-xs px-2 py-1 rounded-full capitalize font-medium"
            style="background:rgba(86,216,255,0.1);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.2)">
            {{ u.role }}
          </span>
          <span class="text-xs px-2 py-1 rounded-full font-medium"
            :style="u.kyc_verified ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)' : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">
            {{ u.kyc_verified ? '✓ KYC Verified' : 'KYC Pending' }}
          </span>
          <span class="text-xs px-2 py-1 rounded-full font-medium"
            :style="u.is_active !== false ? 'background:rgba(111,255,210,0.08);color:var(--accent-teal)' : 'background:rgba(239,68,68,0.08);color:#ef4444'">
            {{ u.is_active !== false ? 'Active' : 'Suspended' }}
          </span>

          <!-- Risk score if present -->
          <span v-if="u.risk_score !== undefined && u.risk_score !== null"
            class="text-xs px-2 py-1 rounded-full font-medium"
            :style="`background:rgba(${u.risk_score>70?'239,68,68':u.risk_score>40?'251,191,36':'111,255,210'},0.08);color:${u.risk_score>70?'#ef4444':u.risk_score>40?'#fbbf24':'#6fffd2'}`">
            Risk: {{ u.risk_score }}%
          </span>

          <div class="flex gap-2">
            <button v-if="!u.kyc_verified" @click="approveKyc(u.id)"
              class="px-3 py-1.5 text-xs rounded-lg font-medium transition-all"
              style="background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)">
              Approve KYC
            </button>
            <button @click="toggleActive(u.id, u.is_active)"
              class="px-3 py-1.5 text-xs rounded-lg font-medium transition-all"
              :style="u.is_active !== false ? 'background:rgba(251,191,36,0.08);color:#fbbf24;border:1px solid rgba(251,191,36,0.2)' : 'background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)'">
              {{ u.is_active !== false ? 'Suspend' : 'Activate' }}
            </button>
            <button @click="changeRole(u)"
              class="px-3 py-1.5 text-xs rounded-lg font-medium transition-all"
              style="background:rgba(167,139,250,0.08);color:var(--accent-violet);border:1px solid rgba(167,139,250,0.2)">
              Change Role
            </button>
            <button @click="openDetail(u)"
              class="px-3 py-1.5 text-xs rounded-lg font-medium transition-all"
              style="background:rgba(86,216,255,0.08);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.2)">
              View Details
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.65)" @click.self="detail = null">
      <div class="w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div v-if="detailLoading" class="py-16 text-center" style="color:var(--text-muted)">Loading...</div>
        <template v-else-if="detail">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-4">
              <div class="w-14 h-14 rounded-full flex items-center justify-center font-black text-xl shrink-0" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ detail.name?.charAt(0)?.toUpperCase() }}</div>
              <div>
                <p class="font-bold text-lg" style="color:var(--text-primary)">{{ detail.name }}</p>
                <p class="text-xs" style="color:var(--text-muted)">{{ detail.email }} · {{ detail.phone || 'no phone' }}</p>
                <p class="text-xs mt-0.5" style="color:var(--text-muted)">Joined {{ new Date(detail.created_at).toLocaleDateString('en-GB') }} · Role: <span class="capitalize">{{ detail.role }}</span></p>
              </div>
            </div>
            <button @click="detail = null" class="text-2xl leading-none" style="color:var(--text-muted)">&times;</button>
          </div>

          <!-- Profile -->
          <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)"><p class="text-xs" style="color:var(--text-muted)">Business Name</p><p style="color:var(--text-primary)">{{ detail.profile?.business_name || '—' }}</p></div>
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)"><p class="text-xs" style="color:var(--text-muted)">Total Listings</p><p style="color:var(--text-primary)">{{ detail.listings?.length ?? 0 }}</p></div>
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)"><p class="text-xs" style="color:var(--text-muted)">KYC Status</p><p style="color:var(--text-primary)">{{ detail.kyc?.status || (detail.kyc_verified ? 'approved' : 'not submitted') }}</p></div>
            <div class="p-3 rounded-xl" style="background:var(--surface-overlay-soft)"><p class="text-xs" style="color:var(--text-muted)">Account Status</p><p style="color:var(--text-primary)">{{ detail.is_active !== false ? 'Active' : 'Suspended' }}</p></div>
          </div>

          <!-- Subscription / Plan -->
          <div class="mb-5">
            <h4 class="font-semibold text-sm mb-2" style="color:var(--text-primary)">Subscription Plan</h4>
            <div class="p-4 rounded-xl flex items-center justify-between flex-wrap gap-3" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
              <div>
                <p class="text-sm font-medium" style="color:var(--text-primary)">{{ detail.active_subscription?.plan?.name || 'No active plan' }}</p>
                <p v-if="detail.active_subscription" class="text-xs" style="color:var(--text-muted)">Status: {{ detail.active_subscription.status }} · Renews {{ detail.active_subscription.current_period_end ? new Date(detail.active_subscription.current_period_end).toLocaleDateString('en-GB') : '—' }}</p>
              </div>
              <div class="flex items-center gap-2">
                <select v-model="planChangeId" class="px-3 py-2 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                  <option value="">Select plan...</option>
                  <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }} — {{ p.price_monthly }}/mo</option>
                </select>
                <button @click="changePlan" :disabled="!planChangeId || planSaving" class="px-3 py-2 rounded-lg text-xs font-bold disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ planSaving ? 'Saving...' : 'Apply' }}</button>
              </div>
            </div>
          </div>

          <!-- Tokens -->
          <div>
            <h4 class="font-semibold text-sm mb-2" style="color:var(--text-primary)">API Tokens</h4>
            <div v-if="tokensLoading" class="text-xs" style="color:var(--text-muted)">Loading tokens...</div>
            <div v-else-if="tokens.length === 0" class="text-xs" style="color:var(--text-muted)">No active tokens.</div>
            <div v-else class="space-y-2">
              <div v-for="t in tokens" :key="t.id" class="flex items-center justify-between p-3 rounded-xl text-xs" style="background:var(--surface-overlay-soft);border:1px solid var(--border-subtle)">
                <div>
                  <p style="color:var(--text-primary)">{{ t.name }}</p>
                  <p style="color:var(--text-muted)">Created {{ new Date(t.created_at).toLocaleDateString('en-GB') }} · Last used {{ t.last_used_at ? new Date(t.last_used_at).toLocaleDateString('en-GB') : 'never' }}</p>
                </div>
                <button @click="revokeToken(t.id)" class="px-3 py-1.5 rounded-lg font-medium" style="background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.2)">Revoke</button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-center gap-2 mt-8">
      <button v-for="p in lastPage" :key="p" @click="goPage(p)"
        class="w-9 h-9 rounded-xl text-xs font-semibold transition-all"
        :style="p === currentPage ? 'background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">
        {{ p }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'

const users = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const roleFilter = ref('')
const kycFilter = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const roles = ['buyer', 'seller', 'dealer', 'agency', 'investor', 'moderator', 'support', 'admin']
let debounceTimer: ReturnType<typeof setTimeout>

async function fetchUsers(page = 1) {
  loading.value = true
  try {
    const r = await client.get('/admin/users', {
      params: { search: search.value, role: roleFilter.value, kyc_verified: kycFilter.value, page }
    })
    users.value = r.data.data || []
    currentPage.value = r.data.current_page || 1
    lastPage.value = r.data.last_page || 1
  } catch {
    users.value = []
  } finally {
    loading.value = false
  }
}

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchUsers(1), 400)
}

function goPage(p: number) { fetchUsers(p) }

onMounted(() => fetchUsers())

async function approveKyc(id: string) {
  await client.post(`/admin/users/${id}/approve-kyc`)
  fetchUsers(currentPage.value)
}

async function toggleActive(id: string, currentlyActive: boolean) {
  await client.post(`/admin/users/${id}/toggle-active`)
  fetchUsers(currentPage.value)
}

async function changeRole(u: any) {
  const newRole = prompt(`Change role for ${u.name}\nCurrent: ${u.role}\nNew role:`, u.role)
  if (!newRole || newRole === u.role) return
  await client.put(`/admin/users/${u.id}/role`, { role: newRole })
  fetchUsers(currentPage.value)
}

// Add User
const showAddModal = ref(false)
const addForm = ref({ name: '', email: '', password: '', role: 'buyer', phone: '', business_name: '' })
const addError = ref('')
const addSaving = ref(false)

function openAdd() {
  addForm.value = { name: '', email: '', password: '', role: 'buyer', phone: '', business_name: '' }
  addError.value = ''
  showAddModal.value = true
}

async function submitAdd() {
  addError.value = ''
  addSaving.value = true
  try {
    await client.post('/admin/users', addForm.value)
    showAddModal.value = false
    fetchUsers(1)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    addError.value = err?.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(', ')
      : err?.response?.data?.message || 'Failed to create user.'
  } finally {
    addSaving.value = false
  }
}

// Detail modal: profile, plan, tokens
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
  } catch {
    tokens.value = []
  } finally {
    tokensLoading.value = false
  }
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
