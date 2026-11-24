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
        <div v-if="provider.provider === 'runcrate'" class="model-catalog" role="group" aria-label="Runcrate models">
          <div v-for="model in provider.models" :key="model" class="model-row">
            <label class="model-choice"><input v-model="provider.model" type="radio" :name="'model-'+provider.provider" :value="model" :disabled="!canManage || busy" /><code>{{ model }}</code></label>
            <span v-if="modelResults[model]" class="model-result">{{ modelResults[model] }}</span>
            <button type="button" :disabled="busy || !provider.enabled || (!provider.configured && !keys[provider.provider]?.trim())" :title="'Test '+model" :aria-label="'Test '+model" @click="test(provider,model)"><PlugZap :size="16" /></button>
          </div>
        </div>
        <div class="fields">
          <label v-if="provider.provider !== 'runcrate'">Model<input v-model="provider.model" :disabled="!canManage || busy" maxlength="150" required /></label>
          <label v-if="canManage">Replacement API key<input v-model="keys[provider.provider]" type="password" autocomplete="new-password" :disabled="busy" maxlength="1000" placeholder="Keep current key" /></label>
        </div>
        <p v-if="provider.key_fingerprint" class="key-state">Saved key: <code>{{ provider.key_fingerprint }}</code> ({{ provider.key_source }})</p>
        <p v-if="keys[provider.provider]?.trim()" class="key-state unsaved" role="status">Replacement key not saved</p>
        <div class="controls"><label><input v-model="provider.enabled" type="checkbox" :disabled="!canManage || busy" /> Enabled</label><label><input type="radio" name="active-provider" :checked="selected === provider.provider" :disabled="!canManage || busy" @change="selected = provider.provider" /> Active</label>
          <button v-if="canManage" type="submit" :disabled="busy"><Save :size="16" /> Save</button><button type="button" :disabled="busy || !provider.enabled || (!provider.configured && !keys[provider.provider]?.trim())" @click="test(provider)"><PlugZap :size="16" /> {{ keys[provider.provider]?.trim() ? 'Test entered key' : 'Test saved key' }}</button>
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

interface Provider { provider:string; configured:boolean; active:boolean; enabled:boolean; model:string; models:string[]; base_url:string; last_http_status:number|null; last_test_result:string|null; tested_at:string|null; key_fingerprint:string|null; key_source:string }
const auth=useAuthStore()
const canManage=computed(()=>auth.can('manage_settings'))
const providers=ref<Provider[]>([]), keys=ref<Record<string,string>>({}), selected=ref('')
const modelResults=ref<Record<string,string>>({})
const busy=ref(false), loading=ref(true), error=ref(''), notice=ref('')
async function load(){
  try { providers.value=(await client.get('/admin/llm/providers')).data; selected.value=providers.value.find(p=>p.active)?.provider??'' }
  catch(e){error.value=apiErrorMessage(e,'Could not load providers.')}
  finally{loading.value=false}
}
async function save(provider:Provider){
  busy.value=true;error.value='';notice.value=''
  try {
    const key=keys.value[provider.provider]?.trim()||''
    const expected=key?Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(key)))).map(byte=>byte.toString(16).padStart(2,'0')).join('').slice(0,12):null
    await client.put('/admin/llm/providers/'+provider.provider,{model:provider.model, enabled:provider.enabled, active:selected.value===provider.provider, ...(key?{api_key:key}:{})})
    const verified:Provider[]=(await client.get('/admin/llm/providers')).data
    const saved=verified.find(p=>p.provider===provider.provider)
    if(!saved || saved.model!==provider.model || saved.enabled!==provider.enabled || (expected && (saved.key_fingerprint!==expected || saved.key_source!=='database')))throw new Error('Save could not be verified. Refresh and try again.')
    providers.value=verified;selected.value=verified.find(p=>p.active)?.provider??''
    keys.value[provider.provider]='';notice.value='Settings saved and verified'+(saved.key_fingerprint?'. Key fingerprint: '+saved.key_fingerprint:'.')
    modelResults.value={}
  }catch(e){error.value=apiErrorMessage(e,'Could not save provider settings.')}
  finally{busy.value=false}
}
async function test(provider:Provider,model=provider.model){
  busy.value=true;error.value='';notice.value=''
  const key=keys.value[provider.provider]?.trim()||''
  try {
    const {data}=await client.post('/admin/llm/test',{provider:provider.provider,model,prompt:'Reply with the single word OK.',...(key?{api_key:key}:{})},{timeout:35000})
    notice.value=data.credential_source==='entered'?'Entered key passed for '+model+'. Not saved yet.':'Saved key passed for '+model+'.'
    modelResults.value[model]=data.credential_source==='entered'?'Entered key passed (unsaved)':'Saved key passed'
  }
  catch(e){error.value=apiErrorMessage(e,'Connection test failed.');modelResults.value[model]='Test failed'}
  finally{
    if(!key){
      try{const fresh:Provider[]=(await client.get('/admin/llm/providers')).data;for(const item of providers.value){const health=fresh.find(p=>p.provider===item.provider);if(health){item.last_http_status=health.last_http_status;item.last_test_result=health.last_test_result;item.tested_at=health.tested_at}}}
      catch{error.value=error.value||'Could not refresh saved connection status.'}
    }
    busy.value=false
  }
}
onMounted(load)
</script>

<style scoped>
.api-management{max-width:1100px;margin:auto;padding:24px;color:var(--text-primary)}header,.provider-heading,.controls{display:flex;gap:16px;align-items:center;flex-wrap:wrap}header{justify-content:space-between}h1{font-size:24px;font-weight:750}h2{font-size:18px;font-weight:700;text-transform:capitalize}.provider{padding:24px 0;border-bottom:1px solid var(--border-subtle)}.provider-heading span,.endpoint,dl{font-size:13px;color:var(--text-secondary)}.endpoint{overflow-wrap:anywhere;margin:12px 0}.fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{font-size:13px} .fields label{display:grid;gap:8px}select,input:not([type=checkbox]):not([type=radio]){width:100%;min-width:0;padding:10px;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-surface);color:var(--text-primary)}.controls{margin:16px 0}.controls label{display:flex;align-items:center;gap:8px}button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 12px;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-surface);color:var(--text-primary);cursor:pointer}button:disabled{opacity:.5;cursor:default}.error{color:var(--danger)}dl{display:grid;grid-template-columns:100px minmax(0,1fr);gap:6px}dd{margin:0;overflow-wrap:anywhere}@media(max-width:650px){.fields{grid-template-columns:1fr}.api-management{padding:16px}}
</style>

<style scoped>
.model-catalog{margin:16px 0;border-top:1px solid var(--border-subtle)}.model-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-subtle);flex-wrap:wrap}.model-choice{display:flex;align-items:center;gap:10px;flex:1;min-width:0}.model-choice code{font-size:13px;overflow-wrap:anywhere}.model-row button{width:36px;height:36px;flex-shrink:0}.model-result,.key-state{font-size:12px;color:var(--text-secondary)}.key-state{margin:12px 0}.unsaved{color:var(--warning)}@media(max-width:650px){.model-choice code{font-size:12px}.model-result{order:3;width:100%}}
</style>
