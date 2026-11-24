<template>
  <div class="p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Plans & <span class="gradient-text">Subscriptions</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">Edit plans shown on the pricing page, record payments and manage subscribers</p>
      </div>
      <div class="flex gap-2">
        <button @click="openGrant()" class="px-4 py-2.5 text-sm font-semibold rounded-xl" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)">Give a plan</button>
        <button @click="openPlan()" class="px-4 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ New plan</button>
      </div>
    </div>

    <p v-if="flash" class="mb-6 text-sm rounded-xl p-3" :style="flash.ok ? 'background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal)' : 'background:rgba(var(--danger-rgb),0.08);color:var(--danger)'">{{ flash.text }}</p>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
      <div v-for="s in statsCards" :key="s.label" class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <p class="text-2xl font-black mb-1" :style="{ color: s.color }">{{ s.value }}</p>
        <p class="text-xs" style="color:var(--text-muted)">{{ s.label }}</p>
      </div>
    </div>

    <!-- Waiting for payment -->
    <section v-if="pending.length" class="rounded-2xl p-6 mb-8" style="background:rgba(var(--warning-rgb),0.05);border:1px solid rgba(var(--warning-rgb),0.3)">
      <h2 class="font-bold mb-1" style="color:var(--text-primary)">Waiting for payment ({{ pending.length }})</h2>
      <p class="text-xs mb-4" style="color:var(--text-muted)">Contact the seller, collect payment by bank transfer or cash, then record it here to activate the plan.</p>
      <div class="space-y-2">
        <div v-for="s in pending" :key="s.id" class="flex flex-wrap items-center gap-3 p-4 rounded-xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex-1 min-w-[200px]">
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ s.user?.name }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ s.user?.email }}<span v-if="s.user?.phone"> · {{ s.user.phone }}</span></p>
          </div>
          <span class="text-xs font-semibold" style="color:var(--text-primary)">{{ s.plan?.name }} · {{ money(s.amount, s.currency) }}/{{ s.billing_cycle === 'yearly' ? 'yr' : 'mo' }}</span>
          <span class="text-xs" style="color:var(--text-muted)">{{ fmtDate(s.created_at) }}</span>
          <button @click="openPayment(s)" class="px-3 py-1.5 text-xs font-bold rounded-lg" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Record payment</button>
          <button @click="cancelSub(s)" class="px-3 py-1.5 text-xs rounded-lg" style="color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.2)">Dismiss</button>
        </div>
      </div>
    </section>

    <!-- Plans -->
    <section class="mb-8">
      <div v-if="loadingPlans" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="h-40 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-for="g in planGroups" v-else :key="g.type" class="mb-6">
        <h2 class="font-bold mb-3" style="color:var(--text-primary)">{{ g.label }}</h2>
        <p v-if="!g.plans.length" class="text-xs" style="color:var(--text-muted)">No plans in this group.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="plan in g.plans" :key="plan.id" class="rounded-2xl p-5 flex flex-col" :style="plan.is_highlighted ? 'background:var(--bg-surface);border:2px solid rgba(var(--accent-teal-rgb),0.35)' : 'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
            <div class="flex items-start justify-between mb-2 gap-2">
              <div>
                <p class="font-black" style="color:var(--text-primary)">{{ plan.name }}</p>
                <p class="text-[11px]" style="color:var(--text-muted)">{{ plan.slug }}<span v-if="plan.is_highlighted"> · Most popular</span></p>
              </div>
              <span class="text-xs px-2 py-0.5 rounded-full" :style="plan.is_active ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)' : 'background:rgba(var(--danger-rgb),0.1);color:var(--danger)'">{{ plan.is_active ? 'On sale' : 'Hidden' }}</span>
            </div>
            <p class="text-2xl font-black" style="color:var(--accent-teal)">{{ Number(plan.price_monthly) > 0 ? money(plan.price_monthly, plan.currency) : 'Free' }}<span v-if="Number(plan.price_monthly) > 0" class="text-xs font-normal" style="color:var(--text-muted)">/mo · {{ money(plan.price_yearly, plan.currency) }}/yr</span></p>
            <ul class="text-xs my-3 space-y-0.5 flex-1" style="color:var(--text-secondary)">
              <li>{{ plan.max_listings }} listings · {{ plan.max_photos_per_listing }} photos</li>
              <li>{{ plan.max_featured }} featured · {{ plan.listing_duration_days }}-day listings</li>
              <li>{{ plan.trial_days ? `${plan.trial_days}-day free trial` : 'No trial' }}</li>
              <li style="color:var(--text-muted)">{{ plan.live_subscribers ?? 0 }} current subscribers</li>
            </ul>
            <div class="flex gap-2">
              <button @click="openPlan(plan)" class="flex-1 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</button>
              <button @click="deletePlan(plan)" class="py-1.5 px-3 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Payment instructions -->
    <section class="rounded-2xl p-6 mb-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="font-bold mb-1" style="color:var(--text-primary)">Payment instructions</h2>
      <p class="text-xs mb-3" style="color:var(--text-muted)">Shown to sellers after they request a paid plan. Include bank name, IBAN, account holder, a reference to use, and your WhatsApp number.</p>
      <textarea v-model="instructions" rows="5" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
      <button @click="saveInstructions" :disabled="saving" class="px-4 py-2 rounded-xl text-sm font-bold disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Save instructions</button>
    </section>

    <!-- Subscribers -->
    <section class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">Subscribers</h2>
        <div class="flex flex-wrap gap-2">
          <input v-model="search" @keyup.enter="fetchSubs(1)" placeholder="Search name, email, phone" class="px-3 py-1.5 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <select v-model="subFilter" @change="fetchSubs(1)" class="px-3 py-1.5 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
            <option value="">All statuses</option>
            <option value="pending">Waiting for payment</option>
            <option value="trial">Free trial</option>
            <option value="active">Active</option>
            <option value="cancelled">Cancelled</option>
            <option value="expired">Expired</option>
          </select>
        </div>
      </div>
      <div v-if="loadingSubs" class="space-y-2">
        <div v-for="i in 5" :key="i" class="h-14 rounded-xl animate-pulse" style="background:var(--surface-overlay)"></div>
      </div>
      <div v-else-if="subs.length === 0" class="text-center py-12" style="color:var(--text-muted)">No subscriptions found.</div>
      <div v-else class="space-y-2">
        <div v-for="s in subs" :key="s.id" class="flex flex-wrap items-center gap-3 p-4 rounded-xl" style="background:var(--surface-overlay-soft);border:1px solid var(--surface-overlay)">
          <div class="flex-1 min-w-[180px]">
            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ s.user?.name }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ s.user?.email }}</p>
          </div>
          <span class="text-xs px-2 py-1 rounded-full font-medium" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal)">{{ s.plan?.name }}</span>
          <span class="text-xs font-semibold" style="color:var(--text-primary)">{{ money(s.amount, s.currency) }}/{{ s.billing_cycle === 'yearly' ? 'yr' : 'mo' }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full" :style="statusSty(s.status)">{{ statusLabel(s.status) }}</span>
          <span class="text-xs" style="color:var(--text-muted)" title="Ends">{{ s.current_period_end ? fmtDate(s.current_period_end) : '—' }}</span>
          <button v-if="['pending','trial','active'].includes(s.status)" @click="openPayment(s)" class="px-3 py-1 text-xs rounded-lg" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)">{{ s.status === 'active' ? 'Renew' : 'Record payment' }}</button>
          <button v-if="['trial','active'].includes(s.status)" @click="editEnd(s)" class="px-3 py-1 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">End date</button>
          <button v-if="['pending','trial','active'].includes(s.status)" @click="cancelSub(s)" class="px-3 py-1 text-xs rounded-lg" style="color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Cancel</button>
        </div>
      </div>
      <div v-if="subLastPage > 1" class="flex flex-wrap gap-2 justify-center mt-4">
        <button v-for="p in subLastPage" :key="p" @click="fetchSubs(p)" class="w-8 h-8 rounded-lg text-xs" :style="p === subPage ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)' : 'background:var(--surface-overlay);color:var(--text-muted)'">{{ p }}</button>
      </div>
    </section>

    <!-- Plan editor -->
    <div v-if="planModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)" @click.self="planModal = false">
      <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">{{ planForm.id ? 'Edit plan' : 'New plan' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label class="field"><span>Name</span><input v-model="planForm.name" /></label>
          <label class="field"><span>Shown to</span>
            <select v-model="planForm.type"><option value="seller">Private sellers</option><option value="dealer">Car dealers</option><option value="agency">Real estate agencies</option></select>
          </label>
          <label class="field sm:col-span-2"><span>Short description</span><input v-model="planForm.description" /></label>
          <label class="field"><span>Monthly price (£)</span><input v-model.number="planForm.price_monthly" type="number" min="0" step="1" /></label>
          <label class="field"><span>Yearly price (£) — blank = 10 × monthly</span><input v-model="planForm.price_yearly" type="number" min="0" step="1" /></label>
          <label class="field"><span>Active listings</span><input v-model.number="planForm.max_listings" type="number" min="0" /></label>
          <label class="field"><span>Photos per listing (max 30)</span><input v-model.number="planForm.max_photos_per_listing" type="number" min="1" max="30" /></label>
          <label class="field"><span>Featured listings</span><input v-model.number="planForm.max_featured" type="number" min="0" /></label>
          <label class="field"><span>Listing duration (days)</span><input v-model.number="planForm.listing_duration_days" type="number" min="7" max="365" /></label>
          <label class="field"><span>Free trial (days, 0 = none)</span><input v-model.number="planForm.trial_days" type="number" min="0" max="365" /></label>
          <label class="field"><span>Order on page</span><input v-model.number="planForm.sort_order" type="number" min="0" /></label>
          <label class="field sm:col-span-2"><span>Extra feature lines (one per line)</span><textarea v-model="planForm.featuresText" rows="3"></textarea></label>
        </div>
        <div class="flex flex-wrap gap-4 mt-4 text-sm" style="color:var(--text-primary)">
          <label class="flex items-center gap-2"><input type="checkbox" v-model="planForm.ai_price_analysis" /> AI price check</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="planForm.ai_trust_score" /> AI trust score</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="planForm.ai_investment_score" /> AI investment score</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="planForm.priority_support" /> Priority support</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="planForm.is_highlighted" /> "Most popular" badge</label>
          <label class="flex items-center gap-2"><input type="checkbox" v-model="planForm.is_active" /> On sale (visible on pricing page)</label>
        </div>
        <div class="flex gap-3 mt-6">
          <button @click="savePlan" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving ? 'Saving…' : 'Save plan' }}</button>
          <button @click="planModal = false" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Record payment -->
    <div v-if="payModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)" @click.self="payModal = null">
      <div class="w-full max-w-md rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="font-bold mb-1" style="color:var(--text-primary)">Record payment</h2>
        <p class="text-xs mb-5" style="color:var(--text-muted)">{{ payModal.sub.user?.name }} · {{ payModal.sub.plan?.name }}. The paid period starts when the current trial or paid period ends.</p>
        <div class="space-y-3">
          <label class="field"><span>Billing</span>
            <select v-model="payModal.billing_cycle" @change="payModal.amount = defaultAmount(payModal.sub, payModal.billing_cycle)"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select>
          </label>
          <label class="field"><span>Amount received (£)</span><input v-model.number="payModal.amount" type="number" min="0" step="0.01" /></label>
          <label class="field"><span>Months to add (blank = 1 for monthly, 12 for yearly)</span><input v-model="payModal.months" type="number" min="1" max="36" /></label>
          <label class="field"><span>Reference (bank ref, receipt no.)</span><input v-model="payModal.reference" /></label>
          <label class="field"><span>Notes</span><input v-model="payModal.notes" /></label>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="recordPayment" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving ? 'Saving…' : 'Activate plan & issue invoice' }}</button>
          <button @click="payModal = null" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Give a plan -->
    <div v-if="grantModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.7)" @click.self="grantModal = false">
      <div class="w-full max-w-md rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="font-bold mb-1" style="color:var(--text-primary)">Give a plan without payment</h2>
        <p class="text-xs mb-5" style="color:var(--text-muted)">For launch partners, compensation or testing. No invoice is created.</p>
        <div class="space-y-3">
          <label class="field"><span>Seller's email</span><input v-model="grantForm.email" type="email" /></label>
          <label class="field"><span>Plan</span>
            <select v-model="grantForm.plan_id">
              <option value="">Select a plan</option>
              <option v-for="p in paidPlans" :key="p.id" :value="p.id">{{ p.name }} — {{ money(p.price_monthly, p.currency) }}/mo</option>
            </select>
          </label>
          <label class="field"><span>Months</span><input v-model.number="grantForm.months" type="number" min="1" max="36" /></label>
          <label class="field"><span>Notes</span><input v-model="grantForm.notes" /></label>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="grant" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving ? 'Saving…' : 'Give plan' }}</button>
          <button @click="grantModal = false" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const plans = ref<any[]>([])
const subs = ref<any[]>([])
const pending = ref<any[]>([])
const statsData = ref<any>({})
const instructions = ref('')
const loadingPlans = ref(true)
const loadingSubs = ref(true)
const saving = ref(false)
const flash = ref<{ ok: boolean; text: string } | null>(null)
const subFilter = ref('')
const search = ref('')
const subPage = ref(1)
const subLastPage = ref(1)

const blankPlan = () => ({
  id: '', name: '', type: 'dealer', description: '', price_monthly: 0, price_yearly: '' as string | number,
  max_listings: 10, max_photos_per_listing: 15, max_featured: 0, listing_duration_days: 90, trial_days: 30, sort_order: 10,
  ai_price_analysis: true, ai_trust_score: false, ai_investment_score: false, priority_support: false,
  is_highlighted: false, is_active: true, featuresText: '',
})
const planModal = ref(false)
const planForm = ref(blankPlan())
const payModal = ref<any>(null)
const grantModal = ref(false)
const grantForm = ref({ email: '', plan_id: '', months: 1, notes: '' })

// Plans from before the launch pricing are kept (existing subscribers) but grouped apart.
const LEGACY = ['free', 'starter', 'professional', 'enterprise']
const planGroups = computed(() => {
  const current = plans.value.filter((p) => !LEGACY.includes(p.slug))
  return [
    { type: 'seller', label: 'Private sellers', plans: current.filter((p) => p.type === 'seller') },
    { type: 'dealer', label: 'Car dealers', plans: current.filter((p) => p.type === 'dealer') },
    { type: 'agency', label: 'Real estate agencies', plans: current.filter((p) => p.type === 'agency') },
    { type: 'other', label: 'Older plans (hidden from the pricing page)', plans: plans.value.filter((p) => LEGACY.includes(p.slug) || !['seller', 'dealer', 'agency'].includes(p.type)) },
  ].filter((g) => g.type !== 'other' || g.plans.length)
})
const paidPlans = computed(() => plans.value.filter((p) => Number(p.price_monthly) > 0))

const statsCards = computed(() => [
  { label: 'Monthly recurring revenue', value: money(statsData.value.mrr ?? 0), color: 'var(--accent-teal)' },
  { label: 'Paying sellers', value: statsData.value.paying ?? 0, color: 'var(--accent-cyan)' },
  { label: 'On free trial', value: statsData.value.trials ?? 0, color: 'var(--accent-violet)' },
  { label: 'Waiting for payment', value: statsData.value.pending ?? 0, color: 'var(--warning)' },
  { label: 'Collected this month', value: money(statsData.value.collected_this_month ?? 0), color: 'var(--text-primary)' },
])

function money(v: any, currency = 'GBP') {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
function fmtDate(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }
function say(ok: boolean, text: string) { flash.value = { ok, text }; setTimeout(() => (flash.value = null), 6000) }

async function fetchPlans() {
  loadingPlans.value = true
  try { plans.value = (await client.get('/admin/subscription-plans')).data || [] } catch { plans.value = [] } finally { loadingPlans.value = false }
}
async function fetchSubs(page = 1) {
  loadingSubs.value = true
  try {
    const r = await client.get('/admin/subscriptions', { params: { status: subFilter.value, search: search.value, page } })
    subs.value = r.data.data || []
    subPage.value = r.data.current_page || 1
    subLastPage.value = r.data.last_page || 1
  } catch { subs.value = [] } finally { loadingSubs.value = false }
}
async function fetchPending() {
  try { pending.value = (await client.get('/admin/subscriptions', { params: { status: 'pending' } })).data.data || [] } catch { pending.value = [] }
}
async function fetchStats() {
  try { statsData.value = (await client.get('/admin/subscriptions/stats')).data } catch { /* keep last */ }
}
async function fetchInstructions() {
  try { instructions.value = (await client.get('/admin/subscriptions/payment-instructions')).data.instructions } catch { /* ignore */ }
}
const refreshAll = () => Promise.all([fetchPlans(), fetchSubs(subPage.value), fetchPending(), fetchStats()])

function openPlan(p?: any) {
  planForm.value = p
    ? { ...blankPlan(), ...p, price_yearly: p.price_yearly, featuresText: (p.features || []).join('\n') }
    : blankPlan()
  planModal.value = true
}
async function savePlan() {
  saving.value = true
  const { id, featuresText, ...rest } = planForm.value as any
  const body: any = { ...rest, features: String(featuresText || '').split('\n').map((s: string) => s.trim()).filter(Boolean) }
  if (body.price_yearly === '' || body.price_yearly === null) delete body.price_yearly
  for (const k of ['slug', 'created_at', 'updated_at', 'deleted_at', 'live_subscribers', 'featured_listing']) delete body[k]
  try {
    if (id) await client.put(`/admin/subscription-plans/${id}`, body)
    else await client.post('/admin/subscription-plans', body)
    planModal.value = false
    say(true, 'Plan saved. The pricing page shows the change immediately.')
    await fetchPlans()
  } catch (e) { say(false, apiErrorMessage(e, 'Could not save the plan.')) } finally { saving.value = false }
}
async function deletePlan(p: any) {
  if (!confirm(`Delete the ${p.name} plan?`)) return
  try { await client.delete(`/admin/subscription-plans/${p.id}`); say(true, 'Plan deleted.'); await fetchPlans() }
  catch (e) { say(false, apiErrorMessage(e, 'Could not delete the plan.')) }
}

function defaultAmount(s: any, cycle: string) {
  return Number(cycle === 'yearly' ? s.plan?.price_yearly : s.plan?.price_monthly) || Number(s.amount) || 0
}
function openPayment(s: any) {
  const cycle = s.billing_cycle || 'monthly'
  payModal.value = { sub: s, billing_cycle: cycle, amount: defaultAmount(s, cycle), months: '', reference: '', notes: '' }
}
async function recordPayment() {
  const m = payModal.value
  saving.value = true
  try {
    await client.post(`/admin/subscriptions/${m.sub.id}/record-payment`, {
      billing_cycle: m.billing_cycle, amount: m.amount, months: m.months ? Number(m.months) : null, reference: m.reference || null, notes: m.notes || null,
    })
    payModal.value = null
    say(true, 'Payment recorded. The plan is active and the seller has been emailed an invoice.')
    await refreshAll()
  } catch (e) { say(false, apiErrorMessage(e, 'Could not record the payment.')) } finally { saving.value = false }
}

function openGrant() { grantForm.value = { email: '', plan_id: '', months: 1, notes: '' }; grantModal.value = true }
async function grant() {
  saving.value = true
  try {
    await client.post('/admin/subscriptions', grantForm.value)
    grantModal.value = false
    say(true, 'Plan given.')
    await refreshAll()
  } catch (e) { say(false, apiErrorMessage(e, 'Could not give the plan.')) } finally { saving.value = false }
}

async function editEnd(s: any) {
  const current = s.current_period_end ? String(s.current_period_end).slice(0, 10) : ''
  const v = prompt('New end date (YYYY-MM-DD):', current)
  if (!v) return
  try { await client.put(`/admin/subscriptions/${s.id}`, { current_period_end: v }); await fetchSubs(subPage.value) }
  catch (e) { say(false, apiErrorMessage(e)) }
}
async function cancelSub(s: any) {
  if (!confirm(s.status === 'pending' ? 'Dismiss this request?' : `Cancel ${s.user?.name}'s plan now?`)) return
  try { await client.delete(`/admin/subscriptions/${s.id}`); await refreshAll() }
  catch (e) { say(false, apiErrorMessage(e)) }
}
async function saveInstructions() {
  saving.value = true
  try { await client.put('/admin/subscriptions/payment-instructions', { instructions: instructions.value }); say(true, 'Payment instructions saved.') }
  catch (e) { say(false, apiErrorMessage(e)) } finally { saving.value = false }
}

function statusLabel(s: string) {
  return ({ pending: 'Waiting for payment', trial: 'Free trial', active: 'Active', cancelled: 'Cancelled', expired: 'Expired' } as Record<string, string>)[s] ?? s
}
function statusSty(s: string) {
  const m: Record<string, string> = {
    active: 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)',
    trial: 'background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)',
    pending: 'background:rgba(var(--warning-rgb),0.12);color:var(--warning)',
    cancelled: 'background:rgba(var(--danger-rgb),0.1);color:var(--danger)',
    expired: 'background:var(--surface-overlay);color:var(--text-muted)',
  }
  return m[s] || m.expired
}

onMounted(() => { refreshAll(); fetchInstructions() })
</script>

<style scoped>
.field { display: flex; flex-direction: column; gap: 4px; }
.field > span { font-size: 12px; color: var(--text-muted); }
.field input, .field select, .field textarea {
  width: 100%; padding: 10px 14px; border-radius: 12px; font-size: 14px;
  background: var(--surface-overlay); border: 1px solid var(--border-strong); color: var(--text-primary);
}
</style>
