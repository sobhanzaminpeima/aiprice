<template>
  <!-- Mobile hamburger button (shown in MainLayout) -->
  <button @click="open = true" class="md:hidden flex flex-col gap-1.5 p-2 rounded-lg transition" style="background:var(--surface-overlay)" aria-label="Menu">
    <span class="block w-5 h-0.5 rounded transition-all" :style="open ? 'transform:rotate(45deg) translateY(6px);background:#6fffd2' : 'background:var(--text-secondary)'"></span>
    <span class="block w-4 h-0.5 rounded transition-all ml-auto" :style="open ? 'opacity:0' : 'background:var(--text-secondary)'"></span>
    <span class="block w-5 h-0.5 rounded transition-all" :style="open ? 'transform:rotate(-45deg) translateY(-6px);background:#6fffd2' : 'background:var(--text-secondary)'"></span>
  </button>

  <!-- Overlay -->
  <Transition name="fade">
    <div v-if="open" @click="open = false" class="md:hidden fixed inset-0 z-40" style="background:rgba(0,0,0,0.6);backdrop-filter:blur(4px)"></div>
  </Transition>

  <!-- Slide-in panel -->
  <Transition name="slide">
    <div v-if="open" class="md:hidden fixed top-0 right-0 bottom-0 z-50 w-72 flex flex-col" style="background:var(--bg-surface);border-left:1px solid rgba(111,255,210,0.15)">
      <!-- Header -->
      <div class="flex items-center justify-between p-5" style="border-bottom:1px solid var(--border-subtle)">
        <span class="font-black text-lg" style="color:var(--text-primary)">AI<span class="gradient-text">Price</span><span style="color:var(--accent-teal)">.app</span></span>
        <button @click="open = false" class="w-8 h-8 rounded-lg flex items-center justify-center text-lg" style="background:var(--surface-overlay);color:var(--text-muted)">×</button>
      </div>

      <!-- Nav links -->
      <nav class="flex-1 p-5 space-y-1">
        <RouterLink v-for="link in links" :key="link.to" :to="link.to" @click="open = false"
          class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all"
          :style="$route.path.startsWith(link.to) ? 'background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)' : 'color:var(--text-secondary);border:1px solid transparent'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" v-html="link.icon"></svg>{{ link.label }}
        </RouterLink>
      </nav>

      <!-- Auth buttons -->
      <div class="p-5 space-y-3" style="border-top:1px solid var(--border-subtle)">
        <template v-if="auth.isAuthenticated">
          <RouterLink :to="auth.homeRoute" @click="open = false" class="block w-full text-center py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Dashboard</RouterLink>
          <button @click="logout" class="block w-full text-center py-3 rounded-xl text-sm font-medium" style="color:var(--text-muted);border:1px solid var(--border-subtle)">Sign Out</button>
        </template>
        <template v-else>
          <RouterLink to="/register" @click="open = false" class="block w-full text-center py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">Get Started Free</RouterLink>
          <RouterLink to="/login" @click="open = false" class="block w-full text-center py-3 rounded-xl text-sm font-medium" style="color:var(--text-secondary);border:1px solid var(--border-strong)">Sign In</RouterLink>
        </template>
      </div>
    </div>
  </Transition>
</template>
<script setup lang="ts">
import { ref } from "vue"; import { RouterLink, useRouter } from "vue-router"; import { useAuthStore } from "@/stores/auth"
const open = ref(false); const auth = useAuthStore(); const router = useRouter()
const links = [
  { to: "/cars", label: "Cars", icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/>' },
  { to: "/properties", label: "Properties", icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>' },
  { to: "/rentals", label: "Rentals", icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1"/>' },
  { to: "/investment-center", label: "Investment Center", icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>' },
  { to: "/market-insights", label: "Market Insights", icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>' },
  { to: "/search", label: "Advanced Search", icon: '<circle cx="11" cy="11" r="7" stroke-linecap="round"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>' },
]
async function logout() { await auth.logout(); open.value = false; router.push("/") }
</script>
<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform 0.3s cubic-bezier(.4,0,.2,1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>