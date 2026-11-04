<template>
  <div style="background:var(--bg-base);min-height:100vh">
    <div class="text-center py-20 px-4" style="background:radial-gradient(ellipse 60% 40% at 50% -10%,rgba(var(--accent-teal-rgb),0.08),transparent)">
      <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold mb-6" style="background:rgba(var(--accent-teal-rgb),0.08);border:1px solid rgba(var(--accent-teal-rgb),0.2);color:var(--accent-teal)">
        AI-Powered Pricing
      </div>
      <h1 class="text-5xl font-black mb-4" style="color:var(--text-primary)">Simple, <span class="gradient-text">Transparent</span> Pricing</h1>
      <p class="text-lg mb-8 max-w-xl mx-auto" style="color:var(--text-muted)">Start free forever. Upgrade when you need AI pricing intelligence, featured listings, or dealer tools.</p>
      <div class="inline-flex items-center gap-1 p-1 rounded-2xl mb-6" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
        <button @click="yearly = false" class="px-5 py-2 rounded-xl text-sm font-semibold transition-all" :style="!yearly ? 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">Monthly</button>
        <button @click="yearly = true" class="px-5 py-2 rounded-xl text-sm font-semibold transition-all" :style="yearly ? 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">
          Yearly <span v-if="maxYearlySaving > 0" class="text-xs ml-1 px-1.5 py-0.5 rounded-full" style="background:rgba(var(--accent-teal-rgb),0.15);color:var(--accent-teal)">save up to {{ maxYearlySaving }}%</span>
        </button>
      </div>
      <p v-if="route.query.checkout === 'cancelled'" class="text-sm" style="color:var(--warning)">Checkout was cancelled — you have not been charged.</p>
      <p v-if="error" class="text-sm mt-2" style="color:var(--danger)">{{ error }}</p>
    </div>

    <div class="max-w-7xl mx-auto px-4 pb-20">
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-96 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-16" :class="plans.length >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'">
        <div v-for="(plan, i) in plans" :key="plan.id" class="rounded-2xl p-6 relative flex flex-col"
          :style="plan.id === featuredId ? 'background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.07),rgba(var(--accent-cyan-rgb),0.04));border:2px solid rgba(var(--accent-teal-rgb),0.3)' : 'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
          <div v-if="plan.id === featuredId" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-xs font-black" style="background:linear-gradient(90deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">MOST POPULAR</div>
          <div v-if="currentPlanId === plan.id" class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)">YOUR PLAN</div>

          <div class="mb-5">
            <h3 class="text-lg font-black mb-1" style="color:var(--text-primary)">{{ plan.name }}</h3>
            <p class="text-xs" style="color:var(--text-muted)">{{ plan.description || taglines[plan.type] || '' }}</p>
          </div>

          <div class="mb-5">
            <div class="flex items-end gap-1">
              <span class="text-3xl font-black" :style="{ color: colors[i % colors.length] }">{{ priceLabel(plan) }}</span>
              <span v-if="!isFree(plan)" class="text-xs mb-1" style="color:var(--text-muted)">/{{ yearly ? 'yr' : 'mo' }}</span>
            </div>
            <p v-if="yearly && !isFree(plan) && saving(plan) > 0" class="text-xs mt-1" style="color:var(--accent-teal)">Save {{ saving(plan) }}% vs monthly</p>
          </div>

          <ul class="space-y-2.5 flex-1 mb-6">
            <li v-for="f in features(plan)" :key="f.text" class="flex items-start gap-2">
              <span class="mt-0.5 shrink-0 text-xs" :style="{ color: f.included ? colors[i % colors.length] : 'var(--text-muted)' }">{{ f.included ? '✓' : '–' }}</span>
              <span class="text-xs leading-relaxed" :style="{ color: f.included ? 'var(--text-secondary)' : 'var(--text-muted)', opacity: f.included ? 1 : 0.6 }">{{ f.text }}</span>
            </li>
          </ul>

          <button @click="choose(plan)" :disabled="busyPlan === plan.id || currentPlanId === plan.id" class="w-full py-3 rounded-xl font-bold text-sm transition-all disabled:opacity-60"
            :style="plan.id === featuredId ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)' : 'background:var(--surface-overlay);color:var(--text-primary);border:1px solid var(--border-subtle)'">
            {{ ctaLabel(plan) }}
          </button>
        </div>
      </div>

      <p v-if="!loading && !billingEnabled" class="text-center text-sm mb-12" style="color:var(--text-muted)">
        Online payment is being set up. To subscribe now, <RouterLink to="/contact" style="color:var(--accent-teal)">contact us</RouterLink>.
      </p>

      <div v-if="plans.length" class="rounded-2xl p-8" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="text-2xl font-black mb-8 text-center" style="color:var(--text-primary)">Full Feature Comparison</h2>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr>
                <th class="text-left pb-5 text-sm font-medium" style="color:var(--text-muted);width:30%">Feature</th>
                <th v-for="(p, i) in plans" :key="p.id" class="pb-5 text-center text-xs font-bold" :style="{ color: colors[i % colors.length] }">{{ p.name }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in comparison" :key="row.label" class="border-t" style="border-color:var(--surface-overlay)">
                <td class="py-3 text-xs" style="color:var(--text-secondary)">{{ row.label }}</td>
                <td v-for="p in plans" :key="p.id" class="py-3 text-center text-xs font-medium">
                  <template v-for="v in [row.value(p)]" :key="String(v)">
                    <span v-if="v === true" style="color:var(--accent-teal)">✓</span>
                    <span v-else-if="v === false" style="color:var(--text-muted)">–</span>
                    <span v-else style="color:var(--text-secondary)">{{ v }}</span>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { usePageMeta } from '@/composables/usePageMeta'

interface Plan {
  id: string; name: string; description: string | null; type: string; currency: string
  price_monthly: string | number; price_yearly: string | number
  max_listings: number; max_photos_per_listing: number
  ai_price_analysis: boolean; ai_trust_score: boolean; ai_investment_score: boolean
  featured_listing: boolean; priority_support: boolean; features: string[] | null
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const yearly = ref(false)
const plans = ref<Plan[]>([])
const loading = ref(true)
const error = ref('')
const billingEnabled = ref(false)
const busyPlan = ref<string | null>(null)
const currentPlanId = ref<string | null>(null)

const colors = ['var(--text-muted)', 'var(--accent-teal)', 'var(--accent-cyan)', 'var(--accent-violet)', 'var(--warning)']
const taglines: Record<string, string> = {
  buyer: 'For buyers who want the full picture', seller: 'For private sellers',
  dealer: 'For dealerships and power sellers', agency: 'For real estate agencies', investor: 'For investors',
}

usePageMeta(() => ({ title: 'Pricing', description: 'Plans for private sellers, dealers and agencies. Start free — upgrade for AI price intelligence and featured listings.' }))

const isFree = (p: Plan) => Number(p.price_monthly) <= 0 && Number(p.price_yearly) <= 0
const priceOf = (p: Plan) => Number(yearly.value ? p.price_yearly : p.price_monthly)
// Yearly prices are set independently in admin, so compute the real saving rather than assuming one.
const saving = (p: Plan) => {
  const m = Number(p.price_monthly) * 12
  return m > 0 ? Math.round((1 - Number(p.price_yearly) / m) * 100) : 0
}
const maxYearlySaving = computed(() => Math.max(0, ...plans.value.map(saving)))

// Highlight the cheapest paid plan that includes AI trust scores, else the middle paid plan.
const featuredId = computed(() => {
  const paid = plans.value.filter((p) => !isFree(p))
  return (paid.find((p) => p.ai_trust_score) ?? paid[Math.floor(paid.length / 2)])?.id ?? null
})

function money(v: number, currency: string) {
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v)
}
function priceLabel(p: Plan) { return isFree(p) ? 'Free' : money(priceOf(p), p.currency) }
function limit(n: number) { return n >= 999 ? 'Unlimited' : String(n) }

function features(p: Plan) {
  return [
    { text: `${limit(p.max_listings)} active listings`, included: true },
    { text: `${limit(p.max_photos_per_listing)} photos per listing`, included: true },
    { text: 'AI fair-price analysis', included: p.ai_price_analysis },
    { text: 'AI trust score', included: p.ai_trust_score },
    { text: 'AI investment score', included: p.ai_investment_score },
    { text: 'Featured listing placement', included: p.featured_listing },
    { text: 'Priority support', included: p.priority_support },
    ...(p.features ?? []).map((text) => ({ text, included: true })),
  ]
}

const comparison = [
  { label: 'Active listings', value: (p: Plan) => limit(p.max_listings) },
  { label: 'Photos per listing', value: (p: Plan) => limit(p.max_photos_per_listing) },
  { label: 'AI fair-price analysis', value: (p: Plan) => p.ai_price_analysis },
  { label: 'AI trust score', value: (p: Plan) => p.ai_trust_score },
  { label: 'AI investment score', value: (p: Plan) => p.ai_investment_score },
  { label: 'Featured placement', value: (p: Plan) => p.featured_listing },
  { label: 'Priority support', value: (p: Plan) => p.priority_support },
]

function ctaLabel(p: Plan) {
  if (currentPlanId.value === p.id) return 'Current plan'
  if (busyPlan.value === p.id) return 'Redirecting to secure checkout…'
  if (isFree(p)) return auth.isAuthenticated ? 'Included' : 'Get started free'
  if (!billingEnabled.value) return 'Contact us'
  return currentPlanId.value ? `Switch to ${p.name}` : `Choose ${p.name}`
}

async function choose(p: Plan) {
  error.value = ''
  if (isFree(p)) {
    router.push(auth.isAuthenticated ? '/dashboard' : '/register')
    return
  }
  if (!billingEnabled.value) {
    router.push('/contact')
    return
  }
  if (!auth.isAuthenticated) {
    router.push({ name: 'register', query: { redirect: '/pricing' } })
    return
  }
  busyPlan.value = p.id
  try {
    const r = await client.post('/billing/checkout', { plan_id: p.id, cycle: yearly.value ? 'yearly' : 'monthly' })
    window.location.href = r.data.url // hosted checkout on the provider's domain
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not start checkout.')
    busyPlan.value = null
  }
}

onMounted(async () => {
  try {
    const [plansRes, statusRes] = await Promise.all([client.get('/subscription-plans'), client.get('/billing/status')])
    plans.value = [...plansRes.data].sort((a: Plan, b: Plan) => Number(a.price_monthly) - Number(b.price_monthly))
    billingEnabled.value = !!statusRes.data?.enabled
    if (auth.isAuthenticated) {
      const sub = (await client.get('/my-subscription')).data
      if (sub?.is_active) currentPlanId.value = sub.subscription?.plan_id ?? null
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load plans.')
  } finally {
    loading.value = false
  }
})
</script>
