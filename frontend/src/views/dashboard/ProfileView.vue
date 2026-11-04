<template>
  <div class="p-8 max-w-2xl">
    <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">My <span class="gradient-text">Profile</span></h1>
    <p class="text-sm mb-8" style="color:var(--text-muted)">Manage your account information</p>
    <div class="rounded-2xl p-6 mb-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <div class="flex items-center gap-4 mb-6">
        <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-black" style="background:linear-gradient(135deg,rgba(var(--accent-teal-rgb),0.2),rgba(var(--accent-cyan-rgb),0.2));color:var(--accent-teal)">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</div>
        <div>
          <p class="font-bold" style="color:var(--text-primary)">{{ auth.user?.name }}</p>
          <p class="text-sm capitalize" style="color:var(--text-muted)">{{ auth.user?.role }}</p>
        </div>
      </div>
      <form @submit.prevent="save" class="space-y-4">
        <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Full Name</label><input v-model="form.name" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" /></div>
        <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Email</label><input v-model="form.email" type="email" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" /></div>
        <div><label class="block text-xs font-medium mb-2" style="color:var(--text-muted)">Phone</label><input v-model="form.phone" placeholder="+90 548 000 0000" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" /></div>
        <div v-if="msg" class="text-xs py-2 px-3 rounded-lg" :style="msg.ok?'background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal)':'background:rgba(var(--danger-rgb),0.08);color:var(--danger)'">{{ msg.text }}</div>
        <button type="submit" :disabled="saving" class="w-full py-3 font-semibold text-sm rounded-xl disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ saving?'Saving...':'Save Changes' }}</button>
      </form>
    </div>
    <div class="rounded-2xl p-6" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
      <h2 class="font-bold mb-4" style="color:var(--text-primary)">Change Password</h2>
      <form @submit.prevent="changePwd" class="space-y-4">
        <input v-model="pwd.current" type="password" placeholder="Current password" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <input v-model="pwd.new_password" type="password" placeholder="New password" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <input v-model="pwd.confirm" type="password" placeholder="Confirm new password" class="w-full px-4 py-3 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        <button type="submit" class="w-full py-3 font-semibold text-sm rounded-xl" style="background:rgba(var(--accent-violet-rgb),0.1);color:var(--accent-violet);border:1px solid rgba(var(--accent-violet-rgb),0.2)">Change Password</button>
      </form>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
const saving = ref(false); const msg = ref<{ok:boolean;text:string}|null>(null)
const form = ref({ name: auth.user?.name||'', email: auth.user?.email||'', phone: (auth.user as any)?.phone||'' })
const pwd = ref({ current:'', new_password:'', confirm:'' })
async function save() { saving.value=true; msg.value=null; try { await client.put('/profile', form.value); await auth.fetchMe(); msg.value={ok:true,text:'Profile updated!'} } catch { msg.value={ok:false,text:'Failed.'} } finally { saving.value=false } }
async function changePwd() { if (pwd.value.new_password!==pwd.value.confirm) { msg.value={ok:false,text:'Passwords do not match'}; return } try { await client.post('/auth/change-password',{current_password:pwd.value.current,password:pwd.value.new_password,password_confirmation:pwd.value.confirm}); msg.value={ok:true,text:'Password changed!'} } catch { msg.value={ok:false,text:'Incorrect password.'} } }
</script>
