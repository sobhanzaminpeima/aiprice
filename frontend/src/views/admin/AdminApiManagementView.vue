<template>
  <main class="api-management">
    <header><h1>API Management</h1><button type="button" :disabled="busy" title="Refresh providers" aria-label="Refresh providers" @click="load"><RefreshCw :size="18" /></button></header>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="notice" role="status">{{ notice }}</p>
    <p v-if="loading">Loading providers...</p>
    <section v-for="provider in providers" :key="provider.provider" class="provider">
      <div class="provider-heading"><h2>{{ provider.provider }}</h2><span>{{ provider.active ? 'Active provider' : 'Standby' }}</span><span>{{ provider.configured ? 'Key configured' : 'Key missing or disabled' }}</span></div>
      <p class="endpoint">{{ provider.base_url }}</p>
      <form @submit.prevent="save(provider)">
        <div class="fields">
          <label>Model<select v-if="provider.provider === 'runcrate'" v-model="provider.model" :disabled="!canManage || busy"><option v-for="model in provider.models" :key="model" :value="model">{{ model }}</option></select><input v-else v-model="provider.model" :disabled="!canManage || busy" maxlength="150" required /></label>
          <label v-if="canManage">Replacement API key<input v-model="keys[provider.provider]" type="password" autocomplete="new-password" :disabled="busy" maxlength="1000" placeholder="Keep current key" /></label>
        </div>
        <div class="controls"><label><input v-model="provider.enabled" type="checkbox" :disabled="!canManage || busy" /> Enabled</label><label><input type="radio" name="active-provider" :checked="selected === provider.provider" :disabled="!canManage || busy" @change="selected = provider.provider" /> Active</label>
          <button v-if="canManage" type="submit" :disabled="busy"><Save :size="16" /> Save</button><button type="button" :disabled="busy || !provider.configured" @click="test(provider)"><PlugZap :size="16" /> Test connection</button>
        </div>
      </form>
      <dl><dt>Last test</dt><dd>{{ provider.last_test_result || 'Not tested' }}<span v-if="provider.last_http_status"> (HTTP {{ provider.last_http_status }})</span></dd><dt>Tested at</dt><dd>{{ provider.tested_at ? new Date(provider.tested_at).toLocaleString() : '-' }}</dd></dl>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RefreshCw, Save, PlugZap } from 'lucide-vue-next'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

interface Provider { provider:string; configured:boolean; active:boolean; enabled:boolean; model:string; models:string[]; base_url:string; last_http_status:number|null; last_test_result:string|null; tested_at:string|null }
const auth=useAuthStore()
const canManage=computed(()=>auth.can('manage_settings'))
const providers=ref<Provider[]>([]), keys=ref<Record<string,string>>({}), selected=ref('')
const busy=ref(false), loading=ref(true), error=ref(''), notice=ref('')
async function load(){
  try { providers.value=(await client.get('/admin/llm/providers')).data; selected.value=providers.value.find(p=>p.active)?.provider??'' }
  catch(e){error.value=apiErrorMessage(e,'Could not load providers.')}
  finally{loading.value=false}
}
async function save(provider:Provider){
  busy.value=true;error.value='';notice.value=''
  try {
    await client.put('/admin/llm/providers/'+provider.provider,{model:provider.model, enabled:provider.enabled, active:selected.value===provider.provider, ...(keys.value[provider.provider]?{api_key:keys.value[provider.provider]}:{})})
    keys.value[provider.provider]='';notice.value='Provider settings saved.';await load()
  }catch(e){error.value=apiErrorMessage(e,'Could not save provider settings.')}
  finally{busy.value=false}
}
async function test(provider:Provider){
  busy.value=true;error.value='';notice.value=''
  try {await client.post('/admin/llm/test',{provider:provider.provider,model:provider.model,prompt:'Reply with the single word OK.'},{timeout:35000});notice.value='Connection test passed.'}
  catch(e){error.value=apiErrorMessage(e,'Connection test failed.')}
  finally{await load();busy.value=false}
}
onMounted(load)
</script>

<style scoped>
.api-management{max-width:1100px;margin:auto;padding:24px;color:var(--text-primary)}header,.provider-heading,.controls{display:flex;gap:16px;align-items:center;flex-wrap:wrap}header{justify-content:space-between}h1{font-size:24px;font-weight:750}h2{font-size:18px;font-weight:700;text-transform:capitalize}.provider{padding:24px 0;border-bottom:1px solid var(--border-subtle)}.provider-heading span,.endpoint,dl{font-size:13px;color:var(--text-secondary)}.endpoint{overflow-wrap:anywhere;margin:12px 0}.fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{font-size:13px} .fields label{display:grid;gap:8px}select,input:not([type=checkbox]):not([type=radio]){width:100%;min-width:0;padding:10px;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-surface);color:var(--text-primary)}.controls{margin:16px 0}.controls label{display:flex;align-items:center;gap:8px}button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-surface);color:var(--text-primary);cursor:pointer}button:disabled{opacity:.5;cursor:default}.error{color:var(--danger)}dl{display:grid;grid-template-columns:100px minmax(0,1fr);gap:6px}dd{margin:0;overflow-wrap:anywhere}@media(max-width:650px){.fields{grid-template-columns:1fr}.api-management{padding:16px}}
</style>
