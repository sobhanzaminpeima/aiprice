<template>
  <div class="p-8 max-w-4xl">
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-3">
        <button @click="router.push('/admin/listings')" class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:var(--surface-overlay);color:var(--text-secondary)">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div>
          <h1 class="text-2xl font-black" style="color:var(--text-primary)">Edit Listing</h1>
          <p class="text-xs" style="color:var(--text-muted)" v-if="listing">#{{ listing.listing_number }} · {{ listing.type }} · owner: {{ listing.user?.name }} ({{ listing.user?.email }})</p>
        </div>
      </div>
      <span v-if="listing" class="text-xs px-2 py-1 rounded-full capitalize font-medium" :style="statusStyle(listing.status)">{{ listing.status }}</span>
    </div>

    <div v-if="loading" class="py-24 text-center" style="color:var(--text-muted)">Loading listing...</div>

    <template v-else-if="listing">
      <p v-if="error" class="mb-4 p-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444">{{ error }}</p>
      <p v-if="saved" class="mb-4 p-3 rounded-xl text-sm" style="background:rgba(111,255,210,0.1);border:1px solid rgba(111,255,210,0.2);color:var(--accent-teal)">Saved.</p>

      <!-- Gallery -->
      <div class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">Photo Gallery</h2>
        <div v-if="images.length === 0" class="text-sm mb-4" style="color:var(--text-muted)">No photos uploaded yet.</div>
        <div v-else class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-4">
          <div v-for="(img, i) in images" :key="img" class="relative aspect-square rounded-xl overflow-hidden group" style="background:var(--surface-overlay)">
            <img :src="img" class="w-full h-full object-cover" />
            <div v-if="img === listing.thumbnail" class="absolute top-1 left-1 px-1.5 py-0.5 rounded text-xs font-bold" style="background:#6fffd2;color:var(--on-accent)">Cover</div>
            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2" style="background:rgba(0,0,0,0.5)">
              <button v-if="img !== listing.thumbnail" @click="setThumbnail(img)" title="Set as cover" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:rgba(111,255,210,0.9);color:#000">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
              </button>
              <button @click="removeImage(img, i)" title="Delete" class="w-8 h-8 rounded-full flex items-center justify-center" style="background:rgba(239,68,68,0.9);color:#fff">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
        </div>
        <label class="inline-block px-4 py-2.5 rounded-xl text-sm font-semibold cursor-pointer" style="background:rgba(86,216,255,0.1);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.2)">
          {{ uploading ? 'Uploading...' : '+ Upload Photos' }}
          <input type="file" multiple accept="image/*" class="hidden" :disabled="uploading" @change="handleUpload" />
        </label>
      </div>

      <!-- Core fields -->
      <div class="rounded-2xl p-6 mb-6 space-y-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold" style="color:var(--text-primary)">Listing Details</h2>
        <div>
          <label class="field-label">Title</label>
          <input v-model="form.title" class="field-input" />
        </div>
        <div>
          <label class="field-label">Description</label>
          <textarea v-model="form.description" rows="5" class="field-input resize-none"></textarea>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div><label class="field-label">Price</label><input v-model.number="form.price" type="number" class="field-input" /></div>
          <div>
            <label class="field-label">Currency</label>
            <select v-model="form.currency" class="field-input"><option>GBP</option><option>USD</option><option>EUR</option><option>TRY</option></select>
          </div>
          <div>
            <label class="field-label">Status</label>
            <select v-model="form.status" class="field-input">
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
              <option value="sold">Sold</option>
              <option value="expired">Expired</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="field-label">City</label><input v-model="form.city" class="field-input" /></div>
          <div><label class="field-label">District</label><input v-model="form.district" class="field-input" /></div>
        </div>
        <div class="flex items-center gap-5">
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.is_featured" /> Featured</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.is_premium" /> Premium</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.price_negotiable" /> Negotiable</label>
        </div>
      </div>

      <!-- Vehicle-specific -->
      <div v-if="listing.type==='vehicle'" class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">Vehicle Details</h2>
        <div class="grid grid-cols-3 gap-3">
          <div><label class="field-label">Year</label><input v-model.number="form.year" type="number" class="field-input" /></div>
          <div><label class="field-label">Mileage</label><input v-model.number="form.mileage" type="number" class="field-input" /></div>
          <div><label class="field-label">Fuel Type</label><input v-model="form.fuel_type" class="field-input" /></div>
          <div><label class="field-label">Transmission</label><input v-model="form.transmission" class="field-input" /></div>
          <div><label class="field-label">Body Type</label><input v-model="form.body_type" class="field-input" /></div>
          <div><label class="field-label">Color</label><input v-model="form.color" class="field-input" /></div>
          <div><label class="field-label">Engine Size</label><input v-model="form.engine_size" class="field-input" /></div>
          <div><label class="field-label">Condition</label><input v-model="form.condition" class="field-input" /></div>
        </div>
      </div>

      <!-- Property-specific -->
      <div v-if="listing.type==='property'" class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-4" style="color:var(--text-primary)">Property Details</h2>
        <div class="grid grid-cols-3 gap-3 mb-3">
          <div><label class="field-label">Bedrooms</label><input v-model.number="form.bedrooms" type="number" class="field-input" /></div>
          <div><label class="field-label">Bathrooms</label><input v-model.number="form.bathrooms" type="number" class="field-input" /></div>
          <div><label class="field-label">Area (m²)</label><input v-model.number="form.area_sqm" type="number" class="field-input" /></div>
          <div><label class="field-label">Floor</label><input v-model.number="form.floor_number" type="number" class="field-input" /></div>
          <div><label class="field-label">Build Year</label><input v-model.number="form.build_year" type="number" class="field-input" /></div>
          <div><label class="field-label">Furnishing</label><input v-model="form.furnishing" class="field-input" /></div>
        </div>
        <div class="flex flex-wrap gap-4">
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.has_parking" /> Parking</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.has_garden" /> Garden</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.has_pool" /> Pool</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="form.has_elevator" /> Elevator</label>
        </div>
      </div>

      <div class="flex justify-end gap-3 sticky bottom-6">
        <button @click="router.push('/admin/listings')" class="px-6 py-3 rounded-xl text-sm font-semibold" style="background:var(--border-subtle);color:var(--text-secondary)">Cancel</button>
        <button @click="save" :disabled="saving" class="px-8 py-3 rounded-xl font-bold text-sm disabled:opacity-40" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '@/api/client'

const route = useRoute()
const router = useRouter()
const id = route.params.id as string

const listing = ref<any>(null)
const loading = ref(true)
const saving = ref(false)
const uploading = ref(false)
const error = ref('')
const saved = ref(false)
const images = ref<string[]>([])
const form = ref<any>({})

async function load() {
  loading.value = true
  try {
    const r = await client.get('/admin/listings/' + id)
    listing.value = r.data
    images.value = Array.isArray(listing.value.images) ? listing.value.images : []
    form.value = { ...listing.value, ...(listing.value.listable || {}) }
  } catch {
    error.value = 'Failed to load listing.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function save() {
  saving.value = true
  error.value = ''
  saved.value = false
  try {
    await client.put('/admin/listings/' + id, form.value)
    saved.value = true
    setTimeout(() => saved.value = false, 2500)
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Save failed.'
  } finally {
    saving.value = false
  }
}

async function handleUpload(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files || input.files.length === 0) return
  uploading.value = true
  try {
    const fd = new FormData()
    Array.from(input.files).forEach(f => fd.append('images[]', f))
    const r = await client.post(`/admin/listings/${id}/upload-images`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    images.value = r.data.images || images.value
    if (!listing.value.thumbnail && images.value.length) listing.value.thumbnail = images.value[0]
  } catch {
    error.value = 'Upload failed.'
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function removeImage(url: string, i: number) {
  if (!confirm('Remove this photo?')) return
  images.value.splice(i, 1)
  await client.delete(`/admin/listings/${id}/images`, { data: { url } })
}

async function setThumbnail(url: string) {
  listing.value.thumbnail = url
  await client.put(`/admin/listings/${id}/thumbnail`, { url })
}

function statusStyle(s: string) {
  const m: Record<string,string> = { active:'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)', pending:'background:rgba(251,191,36,0.1);color:#fbbf24;border:1px solid rgba(251,191,36,0.2)', rejected:'background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2)', sold:'background:rgba(86,216,255,0.1);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.2)', expired:'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-strong)' }
  return m[s] || m.expired
}
</script>
<style scoped>
.field-label { display:block; font-size:12px; font-weight:500; margin-bottom:6px; color:var(--text-muted); }
.field-input { width:100%; padding:10px 14px; border-radius:12px; font-size:13px; outline:none; background:var(--surface-overlay); border:1px solid var(--border-strong); color:var(--text-primary); }
</style>
