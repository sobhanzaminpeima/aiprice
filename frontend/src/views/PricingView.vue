<template>
  <MainLayout>
  <div style="background:var(--bg-base);min-height:100vh">
    <section class="text-center pt-16 pb-10 px-4" style="background:radial-gradient(ellipse 60% 40% at 50% -10%,rgba(var(--accent-teal-rgb),0.08),transparent)">
      <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold mb-6" style="background:rgba(var(--accent-teal-rgb),0.08);border:1px solid rgba(var(--accent-teal-rgb),0.2);color:var(--accent-teal)">
        {{ $t('First month free for dealers and agencies') }}
      </div>
      <h1 class="text-4xl md:text-5xl font-black mb-4" style="color:var(--text-primary)">{{ $t('pricing.title.lead') }} <span class="gradient-text">{{ $t('pricing.title.accent') }}</span></h1>
      <p class="text-base md:text-lg mb-8 max-w-2xl mx-auto" style="color:var(--text-muted)">
        {{ $t('List your cars and properties where buyers see an AI fair-price check. Private sellers list free. Dealers and agencies try any plan free for a month — no card needed.') }}
      </p>

      <div class="inline-flex flex-wrap justify-center gap-1 p-1 rounded-2xl mb-5" role="tablist" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
        <button v-for="a in audiences" :key="a.key" role="tab" :aria-selected="audience === a.key" @click="audience = a.key"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition-all"
          :style="audience === a.key ? 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">{{ $t(a.label) }}</button>
      </div>

      <div v-if="audience !== 'seller'" class="flex justify-center">
        <div class="inline-flex items-center gap-1 p-1 rounded-2xl" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
          <button @click="yearly = false" class="px-5 py-2 rounded-xl text-sm font-semibold transition-all" :style="!yearly ? 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">{{ $t('Monthly') }}</button>
          <button @click="yearly = true" class="px-5 py-2 rounded-xl text-sm font-semibold transition-all" :style="yearly ? 'background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)' : 'color:var(--text-muted)'">
            {{ $t('Yearly') }} <span class="text-xs ml-1 px-1.5 py-0.5 rounded-full" style="background:rgba(var(--accent-teal-rgb),0.15);color:var(--accent-teal)">{{ $t('2 months free') }}</span>
          </button>
        </div>
      </div>
      <p v-if="route.query.checkout === 'cancelled'" class="text-sm mt-4" style="color:var(--warning)">{{ $t('Checkout was cancelled — you have not been charged.') }}</p>
      <p v-if="error" role="alert" class="text-sm mt-4" style="color:var(--danger)">{{ error }}</p>
    </section>

    <div class="max-w-6xl mx-auto px-4 pb-20">
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="i in 3" :key="i" class="h-96 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>

      <template v-else>
        <div class="grid grid-cols-1 gap-4 mb-6" :class="visible.length >= 3 ? 'md:grid-cols-3' : visible.length === 2 ? 'md:grid-cols-2 max-w-3xl mx-auto' : 'max-w-md mx-auto'">
          <article v-for="plan in visible" :key="plan.id" class="rounded-2xl p-6 relative flex flex-col"
            :style="plan.is_highlighted ? 'background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.07),rgba(var(--accent-cyan-rgb),0.04));border:2px solid rgba(var(--accent-teal-rgb),0.35)' : 'background:var(--bg-surface);border:1px solid var(--border-subtle)'">
            <div v-if="plan.is_highlighted" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-xs font-black whitespace-nowrap" style="background:linear-gradient(90deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('MOST POPULAR') }}</div>
            <div v-if="currentPlanId === plan.id" class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[10px] font-bold" style="background:rgba(var(--accent-teal-rgb),0.12);color:var(--accent-teal)">{{ $t('YOUR PLAN') }}</div>

            <h2 class="text-lg font-black mb-1" style="color:var(--text-primary)">{{ $t(plan.name) }}</h2>
            <p class="text-xs mb-5 min-h-[2rem]" style="color:var(--text-muted)">{{ $t(plan.description || '') }}</p>

            <div class="mb-1 flex items-end gap-1">
              <span class="text-4xl font-black" style="color:var(--text-primary)">{{ isFree(plan) ? $t('Free') : money(priceOf(plan), plan.currency) }}</span>
              <span v-if="!isFree(plan)" class="text-xs mb-1.5" style="color:var(--text-muted)">/{{ yearly ? $t('yr') : $t('mo') }}</span>
            </div>
            <p class="text-xs mb-5 min-h-[1rem]" style="color:var(--accent-teal)">
              <template v-if="!isFree(plan) && plan.max_listings > 0">{{ $t('Only {price} per listing a month', { price: money(perListing(plan), plan.currency) }) }}</template>
              <template v-else-if="isFree(plan)">{{ $t('No card, no time limit') }}</template>
            </p>

            <div v-if="!isFree(plan) && plan.trial_days > 0 && trialEligible" class="mb-5 px-3 py-2 rounded-xl text-xs font-semibold text-center" style="background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal)">
              {{ $t('{n} days free, then {price}/mo', { n: plan.trial_days, price: money(Number(plan.price_monthly), plan.currency) }) }}
            </div>

            <ul class="space-y-2.5 mb-6 flex-1">
              <li v-for="f in features(plan)" :key="f.text" class="flex items-start gap-2 text-sm">
                <span aria-hidden="true" :style="{ color: f.included ? 'var(--accent-teal)' : 'var(--text-muted)' }">{{ f.included ? '✓' : '–' }}</span>
                <span :style="{ color: f.included ? 'var(--text-secondary)' : 'var(--text-muted)', textDecoration: f.included ? 'none' : 'line-through' }">{{ f.text }}</span>
              </li>
            </ul>

            <button @click="choose(plan)" :disabled="!!busyPlan || currentPlanId === plan.id"
              class="w-full py-3 rounded-xl font-bold text-sm transition-all disabled:opacity-60"
              :style="plan.is_highlighted ? 'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)' : 'background:var(--surface-overlay);color:var(--text-primary);border:1px solid var(--border-subtle)'">
              {{ ctaLabel(plan) }}
            </button>
          </article>
        </div>

        <p v-if="audience === 'seller'" class="text-center text-sm mb-14" style="color:var(--text-muted)">
          {{ $t('Selling more than a couple of cars or homes?') }}
          <button class="font-semibold underline" style="color:var(--accent-teal)" @click="audience = 'dealer'">{{ $t('See dealer plans') }}</button>
          ·
          <button class="font-semibold underline" style="color:var(--accent-teal)" @click="audience = 'agency'">{{ $t('See agency plans') }}</button>
        </p>
        <p v-else class="text-center text-xs mb-14" style="color:var(--text-muted)">
          {{ $t('Prices in GBP. Pay by bank transfer or cash — no card needed. Cancel any time; your listings stay up until their end date.') }}
        </p>

        <section v-if="visible.length > 1" class="rounded-2xl p-6 md:p-8 mb-14" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h2 class="text-2xl font-black mb-6 text-center" style="color:var(--text-primary)">{{ $t('Compare plans') }}</h2>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[480px]">
              <thead>
                <tr>
                  <th class="text-left pb-4 text-sm font-medium" style="color:var(--text-muted);width:34%">{{ $t('Feature') }}</th>
                  <th v-for="p in visible" :key="p.id" class="pb-4 text-center text-xs font-bold" :style="{ color: p.is_highlighted ? 'var(--accent-teal)' : 'var(--text-primary)' }">{{ $t(p.name) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in comparison" :key="row.label" class="border-t" style="border-color:var(--surface-overlay)">
                  <td class="py-3 text-xs" style="color:var(--text-secondary)">{{ $t(row.label) }}</td>
                  <td v-for="p in visible" :key="p.id" class="py-3 text-center text-xs font-medium">
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
        </section>

        <section class="max-w-3xl mx-auto">
          <h2 class="text-2xl font-black mb-6 text-center" style="color:var(--text-primary)">{{ $t('Questions') }}</h2>
          <details v-for="q in faq" :key="q.q" class="rounded-xl p-4 mb-3" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <summary class="font-semibold text-sm cursor-pointer" style="color:var(--text-primary)">{{ $t(q.q) }}</summary>
            <p class="text-sm mt-2" style="color:var(--text-muted)">{{ $t(q.a) }}</p>
          </details>
        </section>
      </template>
    </div>

    <!-- Manual payment request confirmation -->
    <div v-if="requested" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6)" @click.self="requested = null">
      <div role="dialog" aria-modal="true" class="w-full max-w-md rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h2 class="text-lg font-black mb-2" style="color:var(--text-primary)">{{ $t('Request received') }}</h2>
        <p class="text-sm mb-4" style="color:var(--text-muted)">
          {{ $t('Your {plan} plan starts as soon as we receive your payment of {price}.', { plan: $t(requested.plan), price: requested.price }) }}
        </p>
        <div class="rounded-xl p-4 mb-5 text-sm whitespace-pre-line" style="background:var(--surface-overlay-soft);color:var(--text-secondary)">{{ requested.instructions }}</div>
        <div class="flex gap-3">
          <RouterLink to="/dashboard/subscription" class="flex-1 py-3 rounded-xl font-bold text-sm text-center" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('Go to my plan') }}</RouterLink>
          <button class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)" @click="requested = null">{{ $t('Close') }}</button>
        </div>
      </div>
    </div>
  </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { usePageMeta } from '@/composables/usePageMeta'
import { t } from '@/i18n'
import MainLayout from '@/layouts/MainLayout.vue'

type Audience = 'seller' | 'dealer' | 'agency'

interface Plan {
  id: string; name: string; slug: string; description: string | null; type: Audience; currency: string
  price_monthly: string | number; price_yearly: string | number
  max_listings: number; max_photos_per_listing: number; max_featured: number
  listing_duration_days: number; trial_days: number; is_highlighted: boolean
  ai_price_analysis: boolean; ai_trust_score: boolean; ai_investment_score: boolean
  featured_listing: boolean; priority_support: boolean; features: string[] | null
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const audiences: { key: Audience; label: string }[] = [
  { key: 'seller', label: 'Private seller' },
  { key: 'dealer', label: 'Car dealer' },
  { key: 'agency', label: 'Real estate agency' },
]

const plans = ref<Plan[]>([])
const loading = ref(true)
const error = ref('')
const yearly = ref(false)
const busyPlan = ref<string | null>(null)
const currentPlanId = ref<string | null>(null)
const currentIsTrial = ref(false)
const trialEligible = ref(true)
const requested = ref<{ plan: string; price: string; instructions: string } | null>(null)

function initialAudience(): Audience {
  const q = String(route.query.audience ?? '')
  if (q === 'seller' || q === 'dealer' || q === 'agency') return q
  const role = auth.user?.role
  return role === 'dealer' || role === 'agency' ? role : 'dealer'
}
const audience = ref<Audience>(initialAudience())
watch(audience, (a) => router.replace({ query: { ...route.query, audience: a } }))

usePageMeta(() => ({
  title: t('Pricing'),
  description: 'Free listings for private sellers. Plans for car dealers and estate agencies in North Cyprus from £19/month — first month free.',
}))

const visible = computed(() => plans.value.filter((p) => p.type === audience.value))
const isFree = (p: Plan) => Number(p.price_monthly) <= 0 && Number(p.price_yearly) <= 0
const priceOf = (p: Plan) => Number(yearly.value ? p.price_yearly : p.price_monthly)
const perListing = (p: Plan) => Number(p.price_monthly) / Math.max(1, p.max_listings)

function money(v: number, currency: string) {
  const digits = v < 10 && v % 1 !== 0 ? 2 : 0
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: currency || 'GBP', minimumFractionDigits: digits, maximumFractionDigits: digits }).format(v)
}

function features(p: Plan) {
  const rows = [
    { text: t('{n} active listings', { n: p.max_listings }), included: true },
    { text: t('{n} photos per listing', { n: p.max_photos_per_listing }), included: true },
    { text: t('Listings stay up {n} days', { n: p.listing_duration_days }), included: true },
    { text: t('AI fair-price check on every listing'), included: p.ai_price_analysis },
    { text: p.max_featured > 0 ? t('{n} featured listings at the top of search', { n: p.max_featured }) : t('Featured listings'), included: p.max_featured > 0 },
    { text: t('AI trust score badge'), included: p.ai_trust_score },
    { text: t('AI investment score'), included: p.ai_investment_score },
    { text: t('Priority support'), included: p.priority_support },
  ]
  for (const extra of p.features ?? []) rows.push({ text: t(extra), included: true })
  // Private sellers don't need to see a wall of crossed-out extras.
  return isFree(p) ? rows.filter((r) => r.included) : rows
}

const comparison = [
  { label: 'Active listings', value: (p: Plan) => String(p.max_listings) },
  { label: 'Photos per listing', value: (p: Plan) => String(p.max_photos_per_listing) },
  { label: 'Listing duration (days)', value: (p: Plan) => String(p.listing_duration_days) },
  { label: 'Featured listings', value: (p: Plan) => (p.max_featured > 0 ? String(p.max_featured) : false) },
  { label: 'AI fair-price check', value: (p: Plan) => p.ai_price_analysis },
  { label: 'AI trust score', value: (p: Plan) => p.ai_trust_score },
  { label: 'AI investment score', value: (p: Plan) => p.ai_investment_score },
  { label: 'Priority support', value: (p: Plan) => p.priority_support },
]

const faq = [
  { q: 'Do I need a card for the free month?', a: 'No. Choose a plan and your free month starts immediately. We only ask for payment if you decide to continue.' },
  { q: 'How do I pay?', a: 'By bank transfer or cash at our office. After you choose a plan we show you the payment details, and your plan is activated as soon as payment arrives.' },
  { q: 'What happens when the free month ends?', a: 'We remind you three days before. If you do not continue, your listings stay online until their own end date, and you can keep adding listings within the free limit.' },
  { q: 'Can I change plans later?', a: 'Yes. During your free month you can switch plans at any time. After that, a new plan starts when your current paid period ends, so you never lose days you paid for.' },
  { q: 'Is it really free for private sellers?', a: 'Yes. Private sellers can have two listings live at a time — for example your car and your home — with no fees.' },
]

function ctaLabel(p: Plan) {
  if (currentPlanId.value === p.id) return currentIsTrial.value ? t('On free trial') : t('Current plan')
  if (busyPlan.value === p.id) return t('Please wait…')
  if (isFree(p)) return auth.isAuthenticated ? t('Post a free ad') : t('Start free')
  if (currentIsTrial.value && p.trial_days > 0) return t('Switch trial to {plan}', { plan: t(p.name) })
  if (trialEligible.value && p.trial_days > 0) return t('Start {n}-day free trial', { n: p.trial_days })
  return t('Choose {plan}', { plan: t(p.name) })
}

async function choose(p: Plan) {
  error.value = ''
  if (isFree(p)) {
    router.push(auth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { role: 'seller', redirect: '/dashboard/listings/new' } })
    return
  }
  if (!auth.isAuthenticated) {
    router.push({ name: 'register', query: { role: p.type, redirect: `/pricing?audience=${p.type}` } })
    return
  }
  busyPlan.value = p.id
  try {
    const r = await client.post('/billing/subscribe', { plan_id: p.id, cycle: yearly.value ? 'yearly' : 'monthly' })
    if (r.data.mode === 'checkout') {
      window.location.href = r.data.url // hosted checkout on the provider's domain
      return
    }
    if (r.data.mode === 'trial') {
      router.push({ path: '/dashboard/subscription', query: { started: 'trial' } })
      return
    }
    requested.value = {
      plan: p.name,
      price: `${money(priceOf(p), p.currency)}/${yearly.value ? t('yr') : t('mo')}`,
      instructions: r.data.instructions,
    }
  } catch (e: any) {
    error.value = e?.response?.status === 403 && /verif/i.test(e?.response?.data?.message ?? '')
      ? t('Please confirm your email address first — check your inbox for the link.')
      : apiErrorMessage(e, t('Could not start your plan. Please try again.'))
  } finally {
    busyPlan.value = null
  }
}

onMounted(async () => {
  try {
    const plansRes = await client.get('/subscription-plans')
    plans.value = [...plansRes.data].sort((a: Plan, b: Plan) => Number(a.price_monthly) - Number(b.price_monthly))
    if (auth.isAuthenticated) {
      const sub = (await client.get('/my-subscription')).data
      if (sub?.is_active) currentPlanId.value = sub.subscription?.plan_id ?? null
      currentIsTrial.value = !!sub?.is_trial
      trialEligible.value = !!sub?.trial_eligible
    }
  } catch (e) {
    error.value = apiErrorMessage(e, t('Could not load plans.'))
  } finally {
    loading.value = false
  }
})
</script>
