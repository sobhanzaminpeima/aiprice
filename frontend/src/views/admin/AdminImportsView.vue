<template>
  <div class="p-4 sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl sm:text-3xl font-black mb-1" style="color:var(--text-primary)">Listing <span class="gradient-text">imports</span></h1>
        <p class="text-sm max-w-2xl" style="color:var(--text-muted)">
          Imported listings are saved as drafts. They can be published only after you record what the source has authorized
          (listing data, photos, seller contacts).
        </p>
      </div>
      <button class="px-4 py-2 text-sm rounded-xl font-medium" style="background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)" @click="refresh">
        Refresh
      </button>
    </div>

    <p v-if="error" class="mb-4 rounded-xl p-3 text-sm" style="background:rgba(var(--danger-rgb),0.08);color:var(--danger)">{{ error }}</p>

    <!-- Sources and permission scope -->
    <section v-for="s in sources" :key="s.id" class="rounded-2xl p-5 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex flex-wrap items-center gap-3 mb-3">
        <h2 class="text-lg font-bold" style="color:var(--text-primary)">{{ s.name }}</h2>
        <span class="chip" :style="chipStyle(s.status === 'active' ? 'ok' : 'warn')">{{ s.status === 'active' ? 'Active' : 'Paused' }}</span>
        <a :href="s.base_url" target="_blank" rel="noopener" class="text-xs" style="color:var(--accent-cyan)">{{ s.base_url }}</a>
      </div>

      <div class="grid gap-2 sm:grid-cols-3 mb-3">
        <div v-for="p in permissionFields" :key="p.key" class="rounded-xl p-3" style="background:var(--surface-overlay)">
          <p class="text-xs mb-1" style="color:var(--text-muted)">{{ p.label }}</p>
          <span class="chip" :style="chipStyle(permTone(s[p.key]))">{{ permLabel(s[p.key]) }}</span>
        </div>
      </div>
      <p class="text-xs mb-1" style="color:var(--text-muted)">
        Permission on file: <span style="color:var(--text-secondary)">{{ s.permission_reference || 'None recorded' }}</span>
        <template v-if="s.permission_granted_at"> · granted {{ fmtDate(s.permission_granted_at) }}</template>
        <template v-if="s.permission_max_listings"> · up to {{ s.permission_max_listings }} listings</template>
        · source attribution {{ s.attribution_required ? 'shown' : 'not required' }}
      </p>
      <p v-if="s.notes" class="text-xs mb-4" style="color:var(--text-muted)">{{ s.notes }}</p>

      <div class="flex flex-wrap gap-2 mb-5">
        <button class="btn" :disabled="busy" @click="startRun(s, 'dry_run')">Start dry run</button>
        <button class="btn" :disabled="busy || !lastDryRun(s)" @click="startRun(s, 'import')"
          :title="lastDryRun(s) ? 'Re-check the latest dry-run selection and import it as drafts' : 'Run a dry run first'">
          Import latest dry-run selection
        </button>
        <button class="btn" :disabled="busy" @click="togglePause(s)">{{ s.status === 'active' ? 'Pause source' : 'Resume source' }}</button>
        <button class="btn" @click="editing = editing === s.id ? null : s.id; form = permissionForm(s)">Record permission</button>
      </div>

      <!-- Permission form -->
      <form v-if="editing === s.id" class="rounded-xl p-4 mb-5 grid gap-3 sm:grid-cols-2" style="background:var(--surface-overlay)" @submit.prevent="savePermission(s)">
        <label v-for="p in permissionFields" :key="p.key" class="text-xs" style="color:var(--text-muted)">
          {{ p.label }}
          <select v-model="form[p.key]" class="field">
            <option value="pending_authorization">Pending authorization</option>
            <option value="authorized">Authorized</option>
            <option value="denied">Denied</option>
          </select>
        </label>
        <label class="text-xs" style="color:var(--text-muted)">Granted on
          <input v-model="form.permission_granted_at" type="date" class="field" />
        </label>
        <label class="text-xs sm:col-span-2" style="color:var(--text-muted)">Where the permission is documented (required to authorize)
          <textarea v-model="form.permission_reference" rows="2" class="field" placeholder="e.g. Signed data-sharing agreement, ref. and date" />
        </label>
        <label class="text-xs" style="color:var(--text-muted)">Maximum listings covered (optional)
          <input v-model.number="form.permission_max_listings" type="number" min="1" class="field" />
        </label>
        <label class="text-xs flex items-center gap-2 mt-5" style="color:var(--text-secondary)">
          <input v-model="form.attribution_required" type="checkbox" /> Show source name and original link on listings
        </label>
        <div class="sm:col-span-2 flex gap-2">
          <button class="btn btn-primary" type="submit" :disabled="busy">Save</button>
          <button class="btn" type="button" @click="editing = null">Cancel</button>
        </div>
      </form>

      <!-- Counters -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
        <div v-for="t in tiles(s)" :key="t.label" class="rounded-xl p-3" style="background:var(--surface-overlay)">
          <p class="text-xl font-black" style="color:var(--text-primary)">{{ t.value }}</p>
          <p class="text-xs" style="color:var(--text-muted)">{{ t.label }}</p>
        </div>
      </div>
    </section>

    <!-- Run history -->
    <section class="rounded-2xl p-5 mb-6 overflow-x-auto" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="text-lg font-bold mb-3" style="color:var(--text-primary)">Import history</h2>
      <table class="w-full text-sm min-w-[720px]">
        <thead><tr class="text-left text-xs" style="color:var(--text-muted)">
          <th class="py-2">Started</th><th>Mode</th><th>Status</th><th>Discovered</th><th>Eligible</th><th>Selected</th>
          <th>Imported</th><th>Duplicates</th><th>Rejected</th><th>Images</th><th>Errors</th>
        </tr></thead>
        <tbody>
          <tr v-for="r in runs" :key="r.id" class="border-t" style="border-color:var(--border-subtle);color:var(--text-secondary)">
            <td class="py-2">{{ fmtDate(r.started_at || r.created_at, true) }}</td>
            <td>{{ r.mode === 'dry_run' ? 'Dry run' : 'Import' }}</td>
            <td><span class="chip" :style="chipStyle(r.status === 'completed' ? 'ok' : r.status === 'failed' ? 'bad' : 'warn')" :title="r.error || ''">{{ r.status }}</span></td>
            <td>{{ r.candidates_discovered }}</td><td>{{ r.eligible }}</td><td>{{ r.selected }}</td><td>{{ r.imported }}</td>
            <td>{{ r.skipped_duplicates }}</td><td>{{ r.rejected }}</td><td>{{ r.images_stored }}</td><td>{{ r.errors_count }}</td>
          </tr>
          <tr v-if="!runs.length"><td colspan="11" class="py-4 text-center" style="color:var(--text-muted)">No runs yet.</td></tr>
        </tbody>
      </table>
      <p v-if="runs[0]?.error" class="text-xs mt-3" style="color:var(--danger)">Latest run: {{ runs[0].error }}</p>
    </section>

    <!-- Listings -->
    <section class="rounded-2xl p-5 overflow-x-auto" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <h2 class="text-lg font-bold mr-2" style="color:var(--text-primary)">Source listings</h2>
        <button v-for="f in statusFilters" :key="f.value" class="chip cursor-pointer" :style="filter === f.value ? chipStyle('ok') : chipStyle('muted')" @click="filter = f.value; loadListings()">
          {{ f.label }}
        </button>
      </div>
      <table class="w-full text-sm min-w-[760px]">
        <thead><tr class="text-left text-xs" style="color:var(--text-muted)">
          <th class="py-2">Score</th><th>Vehicle</th><th>Asking price</th><th>Status</th><th>Photos</th><th>Why held back</th><th></th>
        </tr></thead>
        <tbody>
          <tr v-for="l in listings" :key="l.id" class="border-t align-top" style="border-color:var(--border-subtle);color:var(--text-secondary)">
            <td class="py-2 font-semibold" style="color:var(--text-primary)">{{ l.selection_score ?? '–' }}</td>
            <td>
              {{ vehicleName(l) }}
              <a :href="l.canonical_url" target="_blank" rel="noopener" class="block text-xs" style="color:var(--accent-cyan)">Source #{{ l.source_listing_id }}</a>
            </td>
            <td>{{ money(l.data?.asking_price, l.data?.currency) }}</td>
            <td><span class="chip" :style="chipStyle(statusTone(l.status))">{{ l.status }}</span></td>
            <td>{{ l.images_stored }}</td>
            <td class="text-xs max-w-xs">{{ (l.rejection_reasons?.length ? l.rejection_reasons : l.blockers).map(reasonLabel).join(', ') || '–' }}</td>
            <td class="text-right"><button class="btn" @click="open(l)">Review</button></td>
          </tr>
          <tr v-if="!listings.length"><td colspan="7" class="py-4 text-center" style="color:var(--text-muted)">Nothing here.</td></tr>
        </tbody>
      </table>
    </section>

    <!-- Review drawer -->
    <div v-if="detail" class="fixed inset-0 z-50 flex justify-end" style="background:rgba(0,0,0,0.4)" @click.self="detail = null">
      <aside class="w-full max-w-2xl h-full overflow-y-auto p-6" style="background:var(--bg-surface)">
        <div class="flex justify-between items-start mb-4 gap-3">
          <div>
            <h3 class="text-xl font-bold" style="color:var(--text-primary)">{{ vehicleName(detail) }}</h3>
            <a :href="detail.canonical_url" target="_blank" rel="noopener" class="text-xs break-all" style="color:var(--accent-cyan)">{{ detail.canonical_url }}</a>
          </div>
          <button class="btn" @click="detail = null">Close</button>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-4">
          <img v-for="img in detail.images.filter((i: any) => i.thumb_url)" :key="img.id" :src="img.thumb_url" :alt="`Photo ${img.position + 1}`"
            class="w-full rounded-lg object-cover aspect-[4/3]" loading="lazy" />
        </div>
        <p v-if="detail.images.some((i: any) => i.status !== 'stored')" class="text-xs mb-4" style="color:var(--text-muted)">
          {{ detail.images.filter((i: any) => i.status === 'duplicate').length }} exact duplicates removed,
          {{ detail.images.filter((i: any) => i.status === 'failed').length }} failed.
        </p>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm mb-4">
          <template v-for="[k, v] in facts(detail)" :key="k">
            <dt style="color:var(--text-muted)">{{ k }}</dt><dd style="color:var(--text-primary)">{{ v ?? 'Not provided' }}</dd>
          </template>
        </dl>

        <div class="rounded-xl p-3 mb-4 text-xs" style="background:var(--surface-overlay);color:var(--text-secondary)">
          <p><b>Score breakdown:</b> {{ Object.entries(detail.score_breakdown || {}).map(([k, v]) => `${k} ${v}`).join(' · ') }}</p>
          <p><b>Completeness:</b> {{ detail.completeness_score }}% · <b>Extraction confidence:</b> {{ detail.extraction_confidence }}%</p>
          <p><b>Seller:</b> {{ detail.seller.type || 'unknown' }} · contact {{ permLabel(detail.seller.contact_publication_status) }}
            {{ detail.seller.has_phone ? '' : '· no phone stored' }}</p>
          <p v-if="detail.blockers.length"><b>Publication blocked:</b> {{ detail.blockers.map(reasonLabel).join(', ') }}</p>
        </div>

        <details class="mb-4 text-xs" style="color:var(--text-secondary)">
          <summary class="cursor-pointer">Original seller description (internal, not published)</summary>
          <pre class="whitespace-pre-wrap mt-2">{{ detail.source_description || '–' }}</pre>
        </details>

        <div class="flex flex-wrap gap-2">
          <button class="btn btn-primary" :disabled="busy || detail.blockers.length > 0" @click="act(detail, 'approve')">Approve &amp; publish</button>
          <button class="btn" :disabled="busy || detail.status !== 'published'" @click="act(detail, 'unpublish', true)">Unpublish</button>
          <button class="btn" :disabled="busy" @click="act(detail, 'recheck')">Re-check source</button>
          <button class="btn" :disabled="busy || !detail.listing_id" @click="act(detail, 'analyze')">Queue price analysis</button>
          <button class="btn" style="color:var(--danger)" :disabled="busy || detail.status === 'removed'" @click="act(detail, 'remove', true)">Remove (takedown)</button>
          <a v-if="detail.listing?.slug && detail.status === 'published'" :href="`/listings/${detail.listing.slug}`" target="_blank" class="btn">View public page</a>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import client from '@/api/client'

type Perm = 'authorized' | 'pending_authorization' | 'denied'
interface Source {
  id: string; name: string; base_url: string; status: 'active' | 'paused'; notes?: string
  listing_data_permission: Perm; image_permission: Perm; contact_permission: Perm
  attribution_required: boolean; permission_reference?: string; permission_granted_at?: string; permission_max_listings?: number
  stats: Record<string, any>; latest_run?: Run | null
  [k: string]: any
}
interface Run {
  id: string; mode: 'dry_run' | 'import'; status: string; error?: string; created_at: string; started_at?: string
  import_source_id: string; [k: string]: any
}

const sources = ref<Source[]>([])
const runs = ref<Run[]>([])
const listings = ref<any[]>([])
const detail = ref<any | null>(null)
const filter = ref('selected,imported,published,failed')
const busy = ref(false)
const error = ref('')
const editing = ref<string | null>(null)
const form = ref<Record<string, any>>({})

const permissionFields = [
  { key: 'listing_data_permission', label: 'Listing data' },
  { key: 'image_permission', label: 'Photos' },
  { key: 'contact_permission', label: 'Seller contacts' },
] as const
const statusFilters = [
  { value: 'selected,imported,published,failed', label: 'Selected & imported' },
  { value: 'published', label: 'Published' },
  { value: 'eligible', label: 'Eligible, not selected' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'removed', label: 'Removed' },
]
const reasonText: Record<string, string> = {
  listing_data_permission_pending_authorization: 'listing data permission pending',
  image_permission_pending_authorization: 'photo permission pending',
  listing_data_permission_denied: 'listing data permission denied',
  image_permission_denied: 'photo permission denied',
  not_imported: 'not imported yet', no_longer_active_at_source: 'no longer active at source',
  no_stored_images: 'no photos stored', permission_listing_limit_reached: 'permission limit reached',
  missing_price: 'no price', implausible_price: 'implausible price', missing_mileage: 'no mileage',
  insufficient_images: 'too few photos', missing_core_identity: 'make/model/year missing',
  not_a_used_vehicle: 'not a used vehicle', unsupported_category: 'not a car category',
}

function reasonLabel(r: string) {
  if (r.startsWith('duplicate_of:')) return `duplicate of #${r.slice(13)}`
  if (r.startsWith('conflict:')) return `conflicting ${r.slice(9).replace(/_/g, ' ')}`
  return reasonText[r] ?? r.replace(/_/g, ' ')
}
const permLabel = (p: string) => ({ authorized: 'Authorized', pending_authorization: 'Pending authorization', denied: 'Denied' }[p] ?? p)
const permTone = (p: string) => (p === 'authorized' ? 'ok' : p === 'denied' ? 'bad' : 'warn')
const statusTone = (s: string) => (s === 'published' ? 'ok' : ['failed', 'rejected', 'removed'].includes(s) ? 'bad' : 'warn')
function chipStyle(tone: 'ok' | 'warn' | 'bad' | 'muted') {
  const c = { ok: 'var(--accent-teal)', warn: 'var(--warning)', bad: 'var(--danger)', muted: 'var(--text-muted)' }[tone]
  return `color:${c};border:1px solid ${c};background:transparent`
}
const fmtDate = (d?: string, time = false) => (d ? new Date(d).toLocaleString(undefined, time ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }) : '–')
const money = (v?: number, c?: string) => (v ? `${Number(v).toLocaleString()} ${c ?? ''}` : 'Not provided')
const vehicleName = (l: any) => [l.data?.model_year, l.data?.make, l.data?.model, l.data?.variant].filter(Boolean).join(' ') || `#${l.source_listing_id}`
const lastDryRun = (s: Source) => runs.value.find((r) => r.import_source_id === s.id && r.mode === 'dry_run' && r.status === 'completed' && r.selected > 0)

function tiles(s: Source) {
  const r = runs.value.find((x) => x.import_source_id === s.id)
  return [
    { label: 'Candidates (latest run)', value: r?.candidates_discovered ?? 0 },
    { label: 'Eligible (latest run)', value: r?.eligible ?? 0 },
    { label: 'Selected (latest run)', value: r?.selected ?? 0 },
    { label: 'Imported drafts', value: (s.stats.by_status?.imported ?? 0) + (s.stats.by_status?.published ?? 0) },
    { label: 'Published', value: s.stats.by_status?.published ?? 0 },
    { label: 'Rejected', value: s.stats.by_status?.rejected ?? 0 },
    { label: 'Duplicates skipped (latest run)', value: r?.skipped_duplicates ?? 0 },
    { label: 'Photos stored', value: s.stats.images_stored },
    { label: 'Photos public', value: s.stats.images_public },
    { label: 'Contacts pending', value: s.stats.contacts_pending },
    { label: 'Failed', value: s.stats.failed },
    { label: 'Gone at source', value: s.stats.inactive_at_source },
  ]
}

function facts(l: any): [string, any][] {
  const d = l.data || {}
  return [
    ['Asking price', money(d.asking_price, d.currency)], ['≈ GBP', d.normalized_price_gbp ? Number(d.normalized_price_gbp).toLocaleString() : null],
    ['Mileage', d.mileage != null ? `${Number(d.mileage).toLocaleString()} km` : null], ['Year', d.model_year],
    ['Fuel', d.fuel_type], ['Transmission', d.transmission], ['Engine', d.engine_cc ? `${d.engine_cc} cc` : null],
    ['Power', d.horsepower ? `${d.horsepower} hp` : null], ['Body', d.body_type], ['Colour', d.exterior_color],
    ['Steering', d.steering], ['Drivetrain', d.drivetrain], ['Registration', d.registration_status],
    ['Accident history', d.accident_history], ['Service history', d.service_history],
    ['Location', [d.city, d.district].filter(Boolean).join(', ') || null], ['Published at source', d.source_published_at],
    ['Last seen', fmtDate(l.last_seen_at, true)], ['Features', d.features?.length ? d.features.join(', ') : null],
  ]
}

function permissionForm(s: Source) {
  return {
    listing_data_permission: s.listing_data_permission, image_permission: s.image_permission, contact_permission: s.contact_permission,
    permission_reference: s.permission_reference ?? '', permission_granted_at: s.permission_granted_at?.slice(0, 10) ?? '',
    permission_max_listings: s.permission_max_listings ?? null, attribution_required: s.attribution_required,
  }
}

async function guard(fn: () => Promise<unknown>) {
  busy.value = true
  error.value = ''
  try {
    await fn()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Request failed.'
  } finally {
    busy.value = false
  }
}

async function refresh() {
  await guard(async () => {
    const [o, r] = await Promise.all([client.get('/admin/imports'), client.get('/admin/imports/runs')])
    sources.value = o.data.sources
    runs.value = r.data.data
  })
  await loadListings()
}

async function loadListings() {
  const r = await client.get('/admin/imports/listings', { params: { status: filter.value, per_page: 100 } })
  listings.value = r.data.data
}

const startRun = (s: Source, mode: 'dry_run' | 'import') =>
  guard(async () => {
    const from = mode === 'import' ? lastDryRun(s)?.id : undefined
    await client.post(`/admin/imports/sources/${s.id}/runs`, { mode, target: 35, from_run: from })
    await refresh()
  })

const togglePause = (s: Source) =>
  guard(async () => {
    await client.patch(`/admin/imports/sources/${s.id}`, { status: s.status === 'active' ? 'paused' : 'active' })
    await refresh()
  })

const savePermission = (s: Source) =>
  guard(async () => {
    const payload = { ...form.value, permission_max_listings: form.value.permission_max_listings || null, permission_granted_at: form.value.permission_granted_at || null }
    await client.patch(`/admin/imports/sources/${s.id}`, payload)
    editing.value = null
    await refresh()
  })

async function open(l: any) {
  await guard(async () => {
    detail.value = (await client.get(`/admin/imports/listings/${l.id}`)).data.source_listing
  })
}

const act = (l: any, action: string, needsReason = false) =>
  guard(async () => {
    let reason: string | null = null
    if (needsReason) {
      reason = window.prompt(action === 'remove' ? 'Reason for removal (e.g. seller or source request):' : 'Reason for unpublishing:')
      if (!reason) return
    }
    await client.post(`/admin/imports/listings/${l.id}/${action}`, reason ? { reason } : {})
    await refresh()
    if (detail.value) await open(l)
  })

onMounted(refresh)
</script>

<style scoped>
.chip { display: inline-block; font-size: 0.7rem; padding: 0.1rem 0.55rem; border-radius: 999px; white-space: nowrap; }
.btn { padding: 0.45rem 0.9rem; font-size: 0.8rem; border-radius: 0.75rem; font-weight: 500; background: var(--surface-overlay); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-primary { background: linear-gradient(135deg, var(--accent-teal), var(--accent-cyan)); color: var(--on-accent); border: none; }
.field { display: block; width: 100%; margin-top: 0.25rem; padding: 0.45rem 0.6rem; border-radius: 0.6rem; background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border-subtle); }
</style>
