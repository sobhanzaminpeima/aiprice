<template>
  <section class="ai-composer" aria-label="AI listing assistant">
    <div class="composer-heading"><span class="ai-mark">AI</span><div><h2>Photos, notes, ready-to-review listing.</h2><p>Add your photos together and describe the car or property in one box.</p></div></div>
    <label class="photo-drop">Choose all listing photos
      <span>JPG, PNG or WebP · up to 20 photos · 10 MB each. First photo is the cover.</span>
      <input type="file" multiple accept="image/jpeg,image/png,image/webp" @change="selectPhotos" />
    </label>
    <div v-if="photos.length" class="photo-previews">
      <div v-for="(photo,index) in photos" :key="photo.url"><img :src="photo.url" :alt="'Selected photo '+(index+1)" /><button type="button" @click="removePhoto(index)" :aria-label="'Remove photo '+(index+1)">×</button><button class="cover-button" type="button" @click="cover(index)">{{ index === 0 ? 'Cover photo' : 'Use as cover' }}</button></div>
    </div>
    <label class="composer-label">Everything about your listing
      <textarea v-model="text" rows="6" maxlength="12000" dir="auto" placeholder="Example: 2021 Toyota Corolla, automatic, 38,000 km, petrol, £16,500 in Girne. Or describe your apartment, bedrooms, size, furnishing and price. English, فارسی or Türkçe." />
    </label>
    <p class="composer-note">AI uses your text to prepare fields, description and SEO. Photos stay attached to your listing and are not sent to the text model. Check every value before saving.</p>
    <p v-if="error" role="alert" class="composer-error">{{ error }}</p>
    <div class="composer-actions"><button type="button" class="btn" :disabled="busy || text.trim().length < 20" @click="generate">{{ busy ? 'Preparing your draft…' : 'Prepare listing with AI' }}</button><span>{{ text.length }}/12000</span></div>
    <div v-if="draft" class="draft-preview" aria-live="polite">
      <span class="review-label">Draft · review required</span><h3 dir="auto">{{ draft.title || 'Complete the listing title' }}</h3>
      <p class="draft-description" dir="auto">{{ draft.description }}</p>
      <p v-if="draft.missing_required?.length" class="missing-fields">Still needed: {{ draft.missing_required.join(', ') }}</p>
      <dl><dt>SEO title</dt><dd dir="auto">{{ draft.seo_title }}</dd><dt>SEO description</dt><dd dir="auto">{{ draft.seo_description }}</dd></dl>
      <button type="button" class="btn" @click="emit('apply',draft)">Use this draft & review fields →</button>
    </div>
  </section>
</template>
<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import client from '@/api/client'
import type { ListingDraft } from '@/types/listingDraft'
const props = withDefaults(defineProps<{ admin?: boolean }>(), { admin: false })
const emit = defineEmits<{ apply: [draft: ListingDraft]; photos: [files: File[]] }>()
const text=ref(''), error=ref(''), busy=ref(false), draft=ref<ListingDraft|null>(null)
const photos=ref<{file:File;url:string}[]>([])
function selectPhotos(event:Event) {
  const input=event.target as HTMLInputElement
  error.value=''
  for (const file of Array.from(input.files??[])) {
    if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>10*1024*1024){error.value='Use JPG, PNG or WebP photos under 10 MB.';continue}
    if(photos.value.length>=20){error.value='A listing supports up to 20 photos.';break}
    photos.value.push({file,url:URL.createObjectURL(file)})
  }
  emit('photos',photos.value.map(p=>p.file)); input.value=''
}
function removePhoto(index:number){const p=photos.value[index];if(p)URL.revokeObjectURL(p.url);photos.value.splice(index,1);emit('photos',photos.value.map(p=>p.file))}
function cover(index:number){const p=photos.value.splice(index,1)[0];if(p)photos.value.unshift(p);emit('photos',photos.value.map(p=>p.file))}
async function generate(){
  busy.value=true;error.value=''
  try{draft.value=(await client.post(props.admin?'/admin/listings/extract':'/listings/extract',{text:text.value},{timeout:50000})).data.extracted}
  catch(e:unknown){const err=e as {response?:{data?:{message?:string}}};error.value=err.response?.data?.message||'AI could not prepare the draft. Your text and photos are preserved. Please retry or continue manually.'}
  finally{busy.value=false}
}
onBeforeUnmount(()=>photos.value.forEach(p=>URL.revokeObjectURL(p.url)))
</script>
<style scoped>
.ai-composer{padding:25px;border:1px solid rgba(var(--accent-teal-rgb),.3);border-radius:20px;background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),.07),var(--bg-surface));margin-bottom:25px;color:var(--text-primary)}.composer-heading{display:flex;gap:14px;align-items:flex-start;margin-bottom:22px}.ai-mark{background:var(--accent-teal);color:var(--on-accent);border-radius:12px;padding:12px;font-weight:900}.composer-heading h2{font-size:19px;font-weight:750;line-height:1.4;margin:0 0 6px}.composer-heading p,.composer-note{font-size:13px;color:var(--text-secondary);line-height:1.7}.photo-drop{display:grid;gap:8px;border:1px dashed var(--border-strong);border-radius:13px;padding:20px;background:var(--surface-overlay-soft);font-size:14px;font-weight:600;cursor:pointer}.photo-drop span{font-size:12px;font-weight:400;color:var(--text-muted)}.photo-drop input{font-size:12px;margin-top:5px}.composer-label{display:grid;gap:9px;font-size:13px;font-weight:600;margin-top:22px}.composer-label textarea{width:100%;padding:15px;border:1px solid var(--border-strong);border-radius:12px;background:var(--bg-surface);font:400 14px/1.8 inherit;resize:vertical;min-height:140px;color:var(--text-primary)}.composer-actions{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.composer-actions>span{font-size:12px;color:var(--text-muted)}.btn{padding:12px 18px;background:var(--accent-teal);color:var(--on-accent);border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;border:0}.btn:disabled{opacity:.5;cursor:wait}.photo-previews{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:16px}.photo-previews>div{position:relative;overflow:hidden;border-radius:10px}.photo-previews img{width:100%;height:110px;object-fit:cover}.photo-previews button{position:absolute;right:5px;top:5px;border-radius:50%;background:#111c;color:white;width:25px;height:25px;cursor:pointer}.photo-previews .cover-button{position:static;width:100%;border-radius:0;background:var(--surface-overlay);color:var(--text-primary);font-size:10px;height:27px}.composer-error,.missing-fields{font-size:13px;color:var(--danger)}.draft-preview{margin-top:24px;padding:20px;background:var(--bg-surface);border:1px solid var(--border-subtle);border-radius:14px}.draft-preview h3{font-size:18px;font-weight:700;margin:12px 0}.draft-description{white-space:pre-line;font-size:14px;line-height:1.9}.review-label{font-size:11px;color:var(--accent-teal)}dl{font-size:13px;margin:18px 0}dt{font-weight:700;margin-top:12px}dd{color:var(--text-secondary);margin:6px 0}@media(max-width:600px){.ai-composer{padding:18px}.photo-previews{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
