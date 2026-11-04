<template>
  <div class="p-8 max-w-2xl">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Edit <span class="gradient-text">Listing</span></h1>
    <p class="text-sm mb-8" style="color:var(--text-muted)">Update your listing details</p>
    <div v-if="loading" class="space-y-4"><div v-for="i in 5" :key="i" class="h-12 rounded-xl animate-pulse" style="background:var(--bg-surface)"></div></div>
    <form v-else @submit.prevent="save" class="space-y-5 rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Title</label><input v-model="form.title" required class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" /></div>
      <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Description</label><textarea v-model="form.description" rows="4" class="w-full px-4 py-3 rounded-xl text-sm resize-none outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Price</label><input v-model="form.price" type="number" required class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" /></div>
        <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Currency</label><select v-model="form.currency" class="w-full px-4 py-3 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"><option>GBP</option><option>TRY</option><option>USD</option><option>EUR</option></select></div>
      </div>
      <div v-if="msg" class="text-xs py-2 px-3 rounded-lg" :style="msg.ok?'background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal)':'background:rgba(var(--danger-rgb),0.08);color:var(--danger)'">{{ msg.text }}</div>
      <div class="flex gap-3">
        <button type="submit" :disabled="saving" class="flex-1 py-3 font-semibold text-sm rounded-xl disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save Changes' }}</button>
        <RouterLink to="/dashboard/listings" class="flex-1 py-3 text-sm text-center rounded-xl font-medium" style="border:1px solid var(--border-strong);color:var(--text-secondary)">Cancel</RouterLink>
      </div>
    </form>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import client from '@/api/client'
const route = useRoute(); const router = useRouter()
const loading = ref(true); const saving = ref(false); const msg = ref<any>(null)
const form = ref({ title:'', description:'', price:0, currency:'GBP' })
onMounted(async () => { try { const r = await client.get('/listings/'+route.params.id); form.value={title:r.data.title,description:r.data.description,price:r.data.price,currency:r.data.currency} } catch {} finally { loading.value=false } })
async function save() { saving.value=true; msg.value=null; try { await client.put('/listings/'+route.params.id, form.value); msg.value={ok:true,text:'Saved!'}; setTimeout(()=>router.push('/dashboard/listings'),1000) } catch { msg.value={ok:false,text:'Failed.'} } finally { saving.value=false } }
</script>
