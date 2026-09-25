<template>
  <nav class="bottom-tab-bar md:hidden" role="navigation" aria-label="Primary">
    <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="tab" :class="{ active: isActive(tab) }">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" v-html="tab.icon"></svg>
      <span>{{ tab.label }}</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { computed } from 'vue'

const route = useRoute()
const auth = useAuthStore()

const tabs = computed(() => [
  { to: '/', label: 'Home', match: (p: string) => p === '/', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>' },
  { to: '/cars', label: 'Cars', match: (p: string) => p.startsWith('/cars'), icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8 6H5.5A2.5 2.5 0 003 8.5v2A1.5 1.5 0 004.5 12h15a1.5 1.5 0 001.5-1.5v-2A2.5 2.5 0 0018.5 6H16m-8 0l2-3h4l2 3M8 6h8M6 12v5a1 1 0 001 1h1m8-6v5a1 1 0 01-1 1h-1m-6 0h6"/>' },
  { to: '/search', label: 'Search', match: (p: string) => p.startsWith('/search'), icon: '<circle cx="11" cy="11" r="7" stroke-linecap="round"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>' },
  { to: '/properties', label: 'Properties', match: (p: string) => p.startsWith('/properties'), icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1"/>' },
  auth.isAuthenticated
    ? { to: auth.isAdmin ? '/admin' : '/dashboard', label: 'Account', match: (p: string) => p.startsWith('/dashboard') || p.startsWith('/admin'), icon: '<circle cx="12" cy="8" r="4" stroke-linecap="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/>' }
    : { to: '/login', label: 'Sign In', match: (p: string) => p === '/login' || p === '/register', icon: '<circle cx="12" cy="8" r="4" stroke-linecap="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/>' },
])

function isActive(tab: { match: (p: string) => boolean }) {
  return tab.match(route.path)
}
</script>

<style scoped>
.bottom-tab-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 40;
  display: flex;
  align-items: stretch;
  justify-content: space-around;
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
  transition: color 0.15s;
  min-width: 0;
}
.tab span {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.tab.active {
  color: var(--accent-teal);
}
</style>
