<template>
  <div class="p-4 md:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)"><span class="gradient-text">{{ $t('My listings') }}</span></h1>
        <p class="text-sm" style="color:var(--text-muted)">{{ $t('{n} listings total', { n: total }) }}</p>
      </div>
      <RouterLink v-if="!atLimit" to="/dashboard/listings/new" class="px-5 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ {{ $t('New listing') }}</RouterLink>
      <RouterLink v-else to="/pricing" class="px-5 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('Upgrade to add more') }}</RouterLink>
    </div>

    <!-- Plan usage -->
    <div v-if="usage && usage.listing_limit !== null" class="rounded-2xl p-4 mb-6 flex flex-wrap items-center gap-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex-1 min-w-[200px]">
        <div class="flex justify-between text-xs mb-1.5">
          <span style="color:var(--text-muted)">{{ $t('{plan} plan · active listings', { plan: $t(usage.plan_name || 'Private') }) }}</span>
          <span class="font-semibold" style="color:var(--text-primary)">{{ usage.listings }} / {{ usage.listing_limit }}</span>
        </div>
        <div class="h-1.5 rounded-full overflow-hidden" style="background:var(--surface-overlay)">
          <div class="h-full rounded-full" :style="{ width: Math.min(100, (usage.listings / Math.max(1, usage.listing_limit)) * 100) + '%', background: atLimit ? 'var(--danger)' : 'linear-gradient(90deg,var(--accent-teal),var(--accent-cyan))' }"></div>
        </div>
      </div>
      <span v-if="usage.featured_limit" class="text-xs" style="color:var(--text-muted)">★ {{ $t('{used} of {limit} featured', { used: usage.featured, limit: usage.featured_limit }) }}</span>
      <RouterLink to="/dashboard/subscription" class="text-xs font-semibold" style="color:var(--accent-teal)">{{ $t('Manage plan') }} →</RouterLink>
    </div>

    <p v-if="msg" role="status" class="text-sm mb-4" :style="{ color: msgOk ? 'var(--accent-teal)' : 'var(--danger)' }">{{ msg }}</p>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="h-20 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
    </div>
    <div v-else-if="listings.length === 0" class="text-center py-20 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <p class="font-semibold mb-4" style="color:var(--text-primary)">{{ $t('No listings yet') }}</p>
      <RouterLink to="/dashboard/listings/new" class="px-5 py-2.5 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ $t('Create your first listing') }}</RouterLink>
    </div>
    <div v-else class="space-y-3">
      <div v-for="l in listings" :key="l.id" class="rounded-2xl p-4 md:p-5 flex items-center justify-between flex-wrap gap-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="flex items-center gap-4 min-w-0">
          <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0" style="background:var(--surface-overlay)">
            <img v-if="l.thumbnail" :src="l.thumbnail" alt="" class="w-full h-full object-cover" />
          </div>
          <div class="min-w-0">
            <p class="font-semibold text-sm truncate" style="color:var(--text-primary)"><span v-if="l.is_featured" :title="$t('Featured')" style="color:var(--warning)">★ </span>{{ l.title }}</p>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">
              {{ l.city || '—' }} · {{ $t('Views') }}: {{ l.view_count || 0 }}<span v-if="l.status === 'active' && l.expires_at"> · {{ $t('until {date}', { date: fmtDate(l.expires_at) }) }}</span>
            </p>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">
              {{ $t('Uploaded') }} {{ timeAgo(l.created_at) }} · {{ exactTime(l.created_at) }}<template v-if="l.published_at"> · {{ $t('Live since') }} {{ exactTime(l.published_at) }}</template>
            </p>
            <p v-if="l.status === 'rejected' && l.rejection_reason" class="text-xs mt-0.5" style="color:var(--danger)">{{ l.rejection_reason }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-bold text-sm" style="color:var(--accent-teal)">{{ fmt(l.price, l.currency) }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full" :style="sty(l.status)">{{ $t(statusLabel(l.status)) }}</span>
          <button v-if="l.status === 'active' && usage?.featured_limit" @click="toggleFeature(l)" class="px-3 py-1.5 text-xs rounded-lg"
            :style="l.is_featured ? 'background:rgba(var(--warning-rgb),0.12);color:var(--warning);border:1px solid rgba(var(--warning-rgb),0.3)' : 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'">
            {{ l.is_featured ? $t('Unfeature') : $t('Feature') }}
          </button>
          <RouterLink v-if="l.status === 'active'" :to="'/listings/' + l.slug" class="px-3 py-1.5 text-xs rounded-lg" style="background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)">{{ $t('View') }}</RouterLink>
          <RouterLink :to="'/dashboard/listings/' + l.id + '/edit'" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">{{ ['sold','rented','expired','archived'].includes(l.status) ? $t('Relist') : $t('Edit') }}</RouterLink>
          <button v-if="['active','pending'].includes(l.status)" @click="close(l)" class="px-3 py-1.5 text-xs rounded-lg" style="background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)">{{ l.type === 'rental' ? $t('Mark rented') : $t('Mark sold') }}</button>
          <button @click="del(l)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">{{ $t('Delete') }}</button>
        </div>
      </div>
      <div v-if="page < lastPage" class="text-center pt-2">
        <button @click="load(page + 1)" :disabled="loadingMore" class="px-5 py-2 rounded-xl text-sm disabled:opacity-50" style="background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)">{{ loadingMore ? $t('Loading…') : $t('Load more') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { t, locale } from '@/i18n'
import { timeAgo, exactTime } from '@/composables/useListingTime'

const listings = ref<any[]>([])
const usage = ref<any>(null)
const loading = ref(true)
const loadingMore = ref(false)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const msg = ref('')
const msgOk = ref(true)

const atLimit = computed(() => !!usage.value && usage.value.listing_limit !== null && usage.value.listings >= usage.value.listing_limit)

function say(ok: boolean, text: string) { msgOk.value = ok; msg.value = text }

async function load(p = 1) {
  if (p > 1) loadingMore.value = true
  try {
    const r = await client.get('/my-listings', { params: { page: p, per_page: 20 } })
    listings.value = p === 1 ? (r.data.data || []) : [...listings.value, ...(r.data.data || [])]
    page.value = r.data.current_page || 1
    lastPage.value = r.data.last_page || 1
    total.value = r.data.total ?? listings.value.length
  } catch (e) { say(false, apiErrorMessage(e)) } finally { loading.value = false; loadingMore.value = false }
}
async function loadUsage() {
  try { usage.value = (await client.get('/my-subscription')).data.usage } catch { /* optional */ }
}

async function toggleFeature(l: any) {
  try {
    const r = await client.post(`/listings/${l.id}/feature`)
    l.is_featured = r.data.is_featured
    usage.value = r.data.usage
    say(true, l.is_featured ? t('Featured — this listing now appears at the top of search.') : t('No longer featured.'))
  } catch (e) { say(false, apiErrorMessage(e)) }
}
async function close(l: any) {
  const status = l.type === 'rental' ? 'rented' : 'sold'
  if (!confirm(t('Mark this listing as {s}? It will be taken off the marketplace and free up a slot.', { s: t(status) }))) return
  try {
    const r = await client.post(`/listings/${l.id}/close`, { status })
    Object.assign(l, { status, is_featured: false })
    usage.value = r.data.usage
  } catch (e) { say(false, apiErrorMessage(e)) }
}
async function del(l: any) {
  if (!confirm(t('Delete this listing permanently?'))) return
  try {
    await client.delete('/listings/' + l.id)
    listings.value = listings.value.filter((x) => x.id !== l.id)
    total.value--
    loadUsage()
  } catch (e) { say(false, apiErrorMessage(e)) }
}

function fmt(v: number, cur = 'GBP') { return new Intl.NumberFormat('en-GB', { style: 'currency', currency: cur || 'GBP', maximumFractionDigits: 0 }).format(v) }
function fmtDate(d: string) { return new Date(d).toLocaleDateString(locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB', { day: 'numeric', month: 'short' }) }
function statusLabel(s: string) {
  return ({ active: 'Live', pending: 'In review', rejected: 'Rejected', sold: 'Sold', rented: 'Rented', expired: 'Expired', archived: 'Archived', draft: 'Draft' } as Record<string, string>)[s] ?? s
}
function sty(s: string) {
  const m: Record<string, string> = {
    active: 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)',
    pending: 'background:rgba(var(--warning-rgb),0.1);color:var(--warning)',
    rejected: 'background:rgba(var(--danger-rgb),0.1);color:var(--danger)',
  }
  return m[s] || 'background:var(--surface-overlay);color:var(--text-muted)'
}

onMounted(() => { load(1); loadUsage() })
</script>
