<template>
  <!-- Mobile top bar (dashboard) -->
  <div class="md:hidden flex items-center justify-between p-4" style="background:var(--bg-surface);border-bottom:1px solid var(--border-subtle);position:sticky;top:0;z-index:30">
    <RouterLink to="/" class="flex items-center gap-2">
      <BrandLogo :size="24" />
    </RouterLink>
    <button @click="open = true" class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:var(--surface-overlay)" aria-label="Menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
  </div>

  <!-- Slide-in drawer for less-common links -->
  <Transition name="fade">
    <div v-if="open" @click="open = false" class="md:hidden fixed inset-0 z-40" style="background:rgba(0,0,0,0.6);backdrop-filter:blur(4px)"></div>
  </Transition>
  <Transition name="slide">
    <div v-if="open" class="md:hidden fixed top-0 right-0 bottom-0 z-50 w-72 flex flex-col" style="background:var(--bg-surface);border-left:1px solid var(--border-subtle)">
      <div class="flex items-center justify-between p-5" style="border-bottom:1px solid var(--border-subtle)">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs" style="background:linear-gradient(135deg,#6fffd2,#56d8ff);color:var(--on-accent)">{{ auth.user?.name?.charAt(0) }}</div>
          <div class="min-w-0">
            <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ auth.user?.name }}</p>
            <p class="text-xs truncate" style="color:var(--text-muted)">{{ auth.user?.role }}</p>
          </div>
        </div>
        <button @click="open = false" class="w-8 h-8 rounded-lg flex items-center justify-center text-lg shrink-0" style="background:var(--surface-overlay);color:var(--text-muted)">×</button>
      </div>
      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <RouterLink v-for="link in links" :key="link.to" :to="link.to" @click="open = false"
          class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all"
          :style="$route.path === link.to ? 'background:rgba(111,255,210,0.08);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.15)' : 'color:var(--text-secondary);border:1px solid transparent'">
          {{ link.label }}
        </RouterLink>
      </nav>
      <div class="p-4" style="border-top:1px solid var(--border-subtle)">
        <div class="flex items-center gap-2">
          <button @click="logout" class="flex-1 py-2.5 rounded-lg text-xs font-medium transition" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted)">Sign Out</button>
          <ThemeToggle :size="34" />
        </div>
      </div>
    </div>
  </Transition>

  <!-- Bottom tab bar: the everyday dashboard actions -->
  <nav class="flex md:hidden bottom-tab-bar" role="navigation" aria-label="Dashboard">
    <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="tab" :class="{ active: $route.path === tab.to }">
      <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" v-html="tab.icon"></svg>
      <span>{{ tab.label }}</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BrandLogo from '@/components/BrandLogo.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

const open = ref(false)
const auth = useAuthStore()
const router = useRouter()

const tabs = [
  { to: '/dashboard/overview', label: 'Overview', icon: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>' },
  { to: '/dashboard/listings', label: 'Listings', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>' },
  { to: '/dashboard/listings/new', label: 'New', icon: '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v8M8 12h8"/>' },
  { to: '/dashboard/messages', label: 'Inquiries', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.97-4.03 9-9 9-1.5 0-2.9-.37-4.14-1.02L3 21l1.02-3.86A8.96 8.96 0 013 12c0-4.97 4.03-9 9-9s9 4.03 9 9z"/>' },
  { to: '/dashboard/profile', label: 'Profile', icon: '<circle cx="12" cy="8" r="4" stroke-linecap="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/>' },
]

const links = [
  { to: '/dashboard/analytics', label: 'Analytics' },
  { to: '/dashboard/favorites', label: 'Favourites' },
  { to: '/dashboard/kyc', label: 'KYC Verification' },
  { to: '/dashboard/support', label: 'Help & Support' },
  { to: '/dashboard/subscription', label: 'Subscription' },
]

async function logout() { open.value = false; await auth.logout(); router.push('/') }
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-enter-active, .slide-leave-active { transition: transform 0.3s cubic-bezier(.4,0,.2,1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }

.bottom-tab-bar {
  position: fixed;
  left: 0; right: 0; bottom: 0;
  z-index: 40;
  background: var(--nav-bg);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-top: 1px solid var(--border-subtle);
  padding-bottom: env(safe-area-inset-bottom, 0);
}
.tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 8px 2px 6px;
  font-size: 10px;
  font-weight: 600;
  color: var(--text-muted);
  text-decoration: none;
  min-width: 0;
}
.tab span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.tab.active { color: var(--accent-teal); }
</style>
