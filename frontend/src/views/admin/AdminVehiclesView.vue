<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Vehicle &amp; Property <span class="gradient-text">Catalog</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Manage vehicle brands, models and property types</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6">
      <button v-for="t in tabs" :key="t.id" @click="tab=t.id" class="px-4 py-2 rounded-xl text-sm font-semibold" :style="tab===t.id?'background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)':'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">{{ t.label }}</button>
    </div>

    <!-- Vehicle Brands Tab -->
    <div v-if="tab==='brands'">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">Vehicle Brands ({{ brands.length }})</h2>
        <button @click="showBrand=true" class="px-4 py-2 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ Add Brand</button>
      </div>
      <div v-if="loading" class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div v-for="i in 6" :key="i" class="h-20 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <div v-for="b in brands" :key="b.id" class="rounded-2xl p-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-start justify-between mb-2">
            <div>
              <p class="font-semibold" style="color:var(--text-primary)">{{ b.name }}</p>
              <p class="text-xs" style="color:var(--text-muted)">{{ b.country || '-' }} · {{ b.models?.length||0 }} models</p>
            </div>
            <span class="text-xs" :style="b.is_active!==false?'color:var(--accent-teal)':'color:var(--danger)'">{{ b.is_active!==false?'●':'○' }}</span>
          </div>
          <div class="flex gap-2">
            <button @click="editBrand(b)" class="flex-1 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</button>
            <button @click="delBrand(b.id)" class="py-1.5 px-3 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Del</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Vehicle Models Tab -->
    <div v-if="tab==='models'">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">Vehicle Models ({{ models.length }})</h2>
        <button @click="showModel=true" class="px-4 py-2 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ Add Model</button>
      </div>
      <div class="mb-4">
        <select v-model="modelBrandFilter" @change="fetchModels" class="px-4 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
          <option value="">All Brands</option>
          <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div v-if="loading" class="space-y-2"><div v-for="i in 5" :key="i" class="h-14 rounded-xl animate-pulse" style="background:var(--bg-surface)"></div></div>
      <div v-else class="space-y-2">
        <div v-for="m in models" :key="m.id" class="flex items-center justify-between p-4 rounded-xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div>
            <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ m.name }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ brands.find(b=>b.id===m.vehicle_brand_id)?.name || '-' }} · {{ m.year_from||'-' }}{{ m.year_to?'-'+m.year_to:'+' }}</p>
          </div>
          <div class="flex gap-2">
            <button @click="editModel(m)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</button>
            <button @click="delModel(m.id)" class="px-3 py-1.5 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Property Types Tab -->
    <div v-if="tab==='properties'">
      <div class="flex items-center justify-between mb-5">
        <h2 class="font-bold" style="color:var(--text-primary)">Property Types ({{ propertyTypes.length }})</h2>
        <button @click="showPropType=true" class="px-4 py-2 text-sm font-semibold rounded-xl" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">+ Add Type</button>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div v-for="pt in propertyTypes" :key="pt.id" class="rounded-2xl p-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <div class="flex items-center gap-3 mb-3">
            <span class="text-2xl">{{ pt.icon || '🏠' }}</span>
            <div>
              <p class="font-semibold" style="color:var(--text-primary)">{{ pt.name }}</p>
              <span class="text-xs" :style="pt.is_active!==false?'color:var(--accent-teal)':'color:var(--danger)'">{{ pt.is_active!==false?'Active':'Inactive' }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="editPropType(pt)" class="flex-1 py-1.5 text-xs rounded-lg" style="background:rgba(var(--accent-cyan-rgb),0.08);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.2)">Edit</button>
            <button @click="delPropType(pt.id)" class="py-1.5 px-3 text-xs rounded-lg" style="background:rgba(var(--danger-rgb),0.06);color:var(--danger);border:1px solid rgba(var(--danger-rgb),0.15)">Del</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Brand Modal -->
    <div v-if="showBrand||editingBrand" class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.75)" @click.self="showBrand=false;editingBrand=null">
      <div class="w-80 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h3 class="font-bold mb-4" style="color:var(--text-primary)">{{ editingBrand?'Edit Brand':'Add Brand' }}</h3>
        <input v-model="brandForm.name" placeholder="Brand name (e.g. Toyota)" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <input v-model="brandForm.country" placeholder="Country of origin" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <label class="flex items-center gap-2 text-sm mb-4 cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="brandForm.is_active" /> Active</label>
        <div class="flex gap-3">
          <button @click="saveBrand" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save' }}</button>
          <button @click="showBrand=false;editingBrand=null" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Model Modal -->
    <div v-if="showModel||editingModel" class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.75)" @click.self="showModel=false;editingModel=null">
      <div class="w-80 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h3 class="font-bold mb-4" style="color:var(--text-primary)">{{ editingModel?'Edit Model':'Add Model' }}</h3>
        <select v-model="modelForm.vehicle_brand_id" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
          <option value="">Select Brand</option>
          <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
        <input v-model="modelForm.name" placeholder="Model name (e.g. Corolla)" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <div class="grid grid-cols-2 gap-3 mb-4">
          <input v-model="modelForm.year_from" type="number" placeholder="Year from" class="px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <input v-model="modelForm.year_to" type="number" placeholder="Year to" class="px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        </div>
        <div class="flex gap-3">
          <button @click="saveModel" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save' }}</button>
          <button @click="showModel=false;editingModel=null" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Property Type Modal -->
    <div v-if="showPropType||editingPropType" class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.75)" @click.self="showPropType=false;editingPropType=null">
      <div class="w-80 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid rgba(var(--accent-teal-rgb),0.25)">
        <h3 class="font-bold mb-4" style="color:var(--text-primary)">{{ editingPropType?'Edit Property Type':'Add Property Type' }}</h3>
        <input v-model="propTypeForm.name" placeholder="Type name (e.g. Villa)" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <input v-model="propTypeForm.icon" placeholder="Emoji icon (e.g. 🏠)" class="w-full px-4 py-3 rounded-xl text-sm mb-3" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <label class="flex items-center gap-2 text-sm mb-4 cursor-pointer" style="color:var(--text-primary)"><input type="checkbox" v-model="propTypeForm.is_active" /> Active</label>
        <div class="flex gap-3">
          <button @click="savePropType" :disabled="saving" class="flex-1 py-3 rounded-xl font-bold text-sm disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save' }}</button>
          <button @click="showPropType=false;editingPropType=null" class="flex-1 py-3 rounded-xl text-sm" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'
const tab = ref('brands')
const tabs = [{ id:'brands', label:'Vehicle Brands' }, { id:'models', label:'Vehicle Models' }, { id:'properties', label:'Property Types' }]
const brands = ref<any[]>([])
const models = ref<any[]>([])
const propertyTypes = ref<any[]>([])
const loading = ref(false)
const saving = ref(false)
const modelBrandFilter = ref('')
const showBrand = ref(false)
const showModel = ref(false)
const showPropType = ref(false)
const editingBrand = ref<any>(null)
const editingModel = ref<any>(null)
const editingPropType = ref<any>(null)
const brandForm = ref({ name:'', country:'', is_active:true })
const modelForm = ref({ vehicle_brand_id:'', name:'', year_from:'', year_to:'' })
const propTypeForm = ref({ name:'', icon:'🏠', is_active:true })

onMounted(async () => {
  loading.value = true
  try { await Promise.all([fetchBrands(), fetchPropTypes()]) }
  finally { loading.value = false }
})

async function fetchBrands() { try { const r = await client.get('/admin/vehicle-brands'); brands.value = r.data || [] } catch {} }
async function fetchModels() { try { const r = await client.get('/admin/vehicle-models', { params: modelBrandFilter.value ? { brand_id: modelBrandFilter.value } : {} }); models.value = r.data || [] } catch {} }
async function fetchPropTypes() { try { const r = await client.get('/admin/property-types'); propertyTypes.value = r.data || [] } catch {} }

function editBrand(b: any) { editingBrand.value = b; brandForm.value = { name:b.name, country:b.country||'', is_active:b.is_active!==false } }
async function saveBrand() {
  saving.value = true
  try {
    if (editingBrand.value) await client.put('/admin/vehicle-brands/'+editingBrand.value.id, brandForm.value)
    else await client.post('/admin/vehicle-brands', brandForm.value)
    showBrand.value = false; editingBrand.value = null
    brandForm.value = { name:'', country:'', is_active:true }
    await fetchBrands()
  } catch(e:any) { alert(e?.response?.data?.message||'Failed') }
  finally { saving.value = false }
}
async function delBrand(id: string) { if(!confirm('Delete brand?')) return; await client.delete('/admin/vehicle-brands/'+id); await fetchBrands() }

function editModel(m: any) { editingModel.value = m; modelForm.value = { vehicle_brand_id:m.vehicle_brand_id||'', name:m.name, year_from:m.year_from||'', year_to:m.year_to||'' } }
async function saveModel() {
  saving.value = true
  try {
    if (editingModel.value) await client.put('/admin/vehicle-models/'+editingModel.value.id, modelForm.value)
    else await client.post('/admin/vehicle-models', modelForm.value)
    showModel.value = false; editingModel.value = null
    modelForm.value = { vehicle_brand_id:'', name:'', year_from:'', year_to:'' }
    await fetchModels()
  } catch(e:any) { alert(e?.response?.data?.message||'Failed') }
  finally { saving.value = false }
}
async function delModel(id: string) { if(!confirm('Delete model?')) return; await client.delete('/admin/vehicle-models/'+id); await fetchModels() }

function editPropType(pt: any) { editingPropType.value = pt; propTypeForm.value = { name:pt.name, icon:pt.icon||'🏠', is_active:pt.is_active!==false } }
async function savePropType() {
  saving.value = true
  try {
    if (editingPropType.value) await client.put('/admin/property-types/'+editingPropType.value.id, propTypeForm.value)
    else await client.post('/admin/property-types', propTypeForm.value)
    showPropType.value = false; editingPropType.value = null
    propTypeForm.value = { name:'', icon:'🏠', is_active:true }
    await fetchPropTypes()
  } catch(e:any) { alert(e?.response?.data?.message||'Failed') }
  finally { saving.value = false }
}
async function delPropType(id: string) { if(!confirm('Delete property type?')) return; await client.delete('/admin/property-types/'+id); await fetchPropTypes() }
</script>