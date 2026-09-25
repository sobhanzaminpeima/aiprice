<template>
  <div class="p-8">
    <div class="mb-8">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">System <span class="gradient-text">Settings</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Platform configuration and preferences</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Sidebar -->
      <div class="rounded-2xl p-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <button v-for="sec in sections" :key="sec.key" @click="activeSection=sec.key"
          class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-semibold mb-1 transition-all flex items-center gap-2"
          :style="activeSection===sec.key ? 'background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)' : 'color:var(--text-muted);border:1px solid transparent'">
          <span>{{ sec.icon }}</span> {{ sec.label }}
        </button>
      </div>

      <!-- Content -->
      <div class="lg:col-span-3 space-y-4">

        <!-- Logo Upload — always shown at top of General section -->
        <div v-if="activeSection === 'general'" class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h3 class="font-bold text-sm mb-5" style="color:var(--text-secondary)">Brand Identity</h3>
          <div class="flex items-start gap-6">
            <!-- Preview -->
            <div class="shrink-0">
              <p class="text-xs mb-2" style="color:var(--text-muted)">Current Logo</p>
              <div class="w-24 h-24 rounded-2xl flex items-center justify-center overflow-hidden" style="background:var(--surface-overlay);border:1px solid var(--border-strong)">
                <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-contain p-2" alt="Logo" />
                <div v-else class="flex flex-col items-center gap-1">
                  <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">A</div>
                  <span class="text-xs font-bold" style="color:var(--text-primary)">AIPrice<span style="color:var(--accent-teal)">.app</span></span>
                </div>
              </div>
            </div>

            <!-- Upload area -->
            <div class="flex-1">
              <p class="text-sm font-medium mb-1" style="color:var(--text-primary)">Upload New Logo</p>
              <p class="text-xs mb-4" style="color:var(--text-muted)">PNG or SVG recommended · Max 2MB · Will be shown in header and favicon</p>

              <label class="block cursor-pointer">
                <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp" class="hidden" @change="onLogoFile" ref="logoInput" />
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all"
                  style="background:rgba(111,255,210,0.04);border:1.5px dashed rgba(111,255,210,0.25)"
                  @dragover.prevent @drop.prevent="onLogoDrop">
                  <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  <div>
                    <p class="text-xs font-semibold" style="color:var(--accent-teal)">Click to upload or drag & drop</p>
                    <p class="text-xs" style="color:var(--text-muted)">PNG, SVG, JPG, WEBP</p>
                  </div>
                </div>
              </label>

              <div v-if="logoFile" class="mt-3 flex items-center gap-3">
                <div class="flex-1 flex items-center gap-2 px-3 py-2 rounded-lg" style="background:var(--surface-overlay);border:1px solid var(--border-subtle)">
                  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#6fffd2" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  <span class="text-xs truncate" style="color:var(--text-secondary)">{{ logoFile.name }}</span>
                  <span class="text-xs shrink-0" style="color:var(--text-muted)">{{ (logoFile.size / 1024).toFixed(0) }} KB</span>
                </div>
                <button @click="uploadLogo" :disabled="logoUploading"
                  class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0"
                  style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">
                  {{ logoUploading ? 'Uploading…' : 'Upload' }}
                </button>
                <button @click="clearLogo" class="px-3 py-2 rounded-xl text-xs" style="background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.15)">Remove</button>
              </div>

              <p v-if="logoMsg" class="mt-2 text-xs" :style="logoMsg.ok ? 'color:var(--accent-teal)' : 'color:#ef4444'">{{ logoMsg.text }}</p>
            </div>
          </div>
        </div>

        <!-- Regular setting groups -->
        <div v-for="group in activeGroups" :key="group.label" class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <h3 class="font-bold text-sm mb-5" style="color:var(--text-secondary)">{{ group.label }}</h3>
          <div class="space-y-4">
            <div v-for="field in group.fields" :key="field.key" class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium" style="color:var(--text-primary)">{{ field.label }}</p>
                <p v-if="field.desc" class="text-xs" style="color:var(--text-muted)">{{ field.desc }}</p>
              </div>
              <div>
                <label v-if="field.type==='toggle'" class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="field.value" class="sr-only peer" />
                  <div class="w-10 h-5 rounded-full peer-checked:after:translate-x-5 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:transition-all" :style="field.value ? 'background:#6fffd2' : 'background:var(--border-strong)'"></div>
                </label>
                <input v-else-if="field.type==='text'" v-model="field.value" class="px-3 py-1.5 rounded-lg text-xs w-48" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
                <select v-else-if="field.type==='select'" v-model="field.value" class="px-3 py-1.5 rounded-lg text-xs" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
                  <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button class="px-6 py-2.5 rounded-xl font-bold text-sm" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Save Changes</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, computed } from "vue"
import client from "@/api/client"

const activeSection = ref("general")

// Logo upload state
const logoPreview = ref<string | null>(null)
const logoFile = ref<File | null>(null)
const logoUploading = ref(false)
const logoMsg = ref<{ ok: boolean; text: string } | null>(null)
const logoInput = ref<HTMLInputElement | null>(null)

function onLogoFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
  logoMsg.value = null
}

function onLogoDrop(e: DragEvent) {
  const file = e.dataTransfer?.files?.[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
  logoMsg.value = null
}

function clearLogo() {
  logoFile.value = null
  logoPreview.value = null
  logoMsg.value = null
  if (logoInput.value) logoInput.value.value = ''
}

async function uploadLogo() {
  if (!logoFile.value) return
  logoUploading.value = true
  logoMsg.value = null
  try {
    const form = new FormData()
    form.append('logo', logoFile.value)
    await client.post('/admin/settings/logo', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    logoMsg.value = { ok: true, text: 'Logo uploaded successfully.' }
    logoFile.value = null
  } catch {
    logoMsg.value = { ok: false, text: 'Upload failed. Check file size and format.' }
  } finally {
    logoUploading.value = false
  }
}

const sections = [
  { key: "general", icon: "S", label: "General" },
  { key: "ai", icon: "R", label: "AI Engine" },
  { key: "listings", icon: "L", label: "Listings" },
  { key: "users", icon: "U", label: "Users & KYC" },
  { key: "notifications", icon: "N", label: "Notifications" },
  { key: "security", icon: "P", label: "Security" },
]
const allGroups: Record<string,any[]> = {
  general: [
    { label: "Site Info", fields: [
      { key:"site_name", label:"Site Name", type:"text", value:"AIPrice.app" },
      { key:"site_tagline", label:"Tagline", type:"text", value:"Truth In Every Transaction" },
      { key:"maintenance", label:"Maintenance Mode", desc:"Take the site offline for maintenance", type:"toggle", value:false },
    ]},
    { label: "Currency", fields: [
      { key:"default_currency", label:"Default Currency", type:"select", value:"TRY", options:["TRY","GBP","USD","EUR"] },
      { key:"show_multi_currency", label:"Show Multiple Currencies", type:"toggle", value:true },
    ]},
  ],
  ai: [
    { label: "AI Analysis", fields: [
      { key:"ai_enabled", label:"AI Price Analysis", desc:"Run AI analysis on all new listings", type:"toggle", value:true },
      { key:"auto_trust_score", label:"Auto-Assign Trust Scores", type:"toggle", value:true },
      { key:"fraud_ai", label:"AI Fraud Detection", type:"toggle", value:true },
      { key:"confidence_threshold", label:"Min. Confidence Threshold", type:"select", value:"70%", options:["50%","60%","70%","80%","90%"] },
    ]},
  ],
  listings: [
    { label: "Listing Rules", fields: [
      { key:"require_photos", label:"Require Min 5 Photos", type:"toggle", value:true },
      { key:"auto_expire", label:"Auto-Expire Listings (days)", type:"text", value:"60" },
      { key:"manual_approval", label:"Manual Approval Required", type:"toggle", value:false },
      { key:"free_limit", label:"Free Plan Listing Limit", type:"text", value:"3" },
    ]},
  ],
  users: [
    { label: "Registration", fields: [
      { key:"email_verify", label:"Require Email Verification", type:"toggle", value:true },
      { key:"kyc_required", label:"KYC for Dealers", type:"toggle", value:true },
      { key:"allow_social", label:"Allow Social Login", type:"toggle", value:true },
    ]},
  ],
  notifications: [
    { label: "Email Notifications", fields: [
      { key:"new_listing_email", label:"New Listing to Admin", type:"toggle", value:true },
      { key:"fraud_email", label:"Fraud Alert Email", type:"toggle", value:true },
      { key:"inquiry_email", label:"Inquiry Email to Sellers", type:"toggle", value:true },
    ]},
  ],
  security: [
    { label: "Security", fields: [
      { key:"two_factor", label:"Force 2FA for Admins", type:"toggle", value:false },
      { key:"ip_rate_limit", label:"IP Rate Limiting", type:"toggle", value:true },
      { key:"session_timeout", label:"Session Timeout (hours)", type:"text", value:"8" },
    ]},
  ],
}
const activeGroups = computed(() => allGroups[activeSection.value] || [])
</script>