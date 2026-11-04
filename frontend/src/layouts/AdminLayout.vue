<template>
  <div class="admin-shell">
    <!-- Mobile top bar -->
    <header class="admin-topbar md:hidden">
      <button type="button" class="icon-btn" :aria-expanded="sidebarOpen" aria-controls="admin-sidebar" @click="sidebarOpen = !sidebarOpen">
        <Menu :size="18" aria-hidden="true" /><span class="sr-only">Menu</span>
      </button>
      <BrandLogo :size="24" />
      <NotificationBell align="right" />
    </header>

    <div v-if="sidebarOpen" class="admin-scrim md:hidden" @click="sidebarOpen = false"></div>

    <aside id="admin-sidebar" class="admin-sidebar" :class="{ 'admin-sidebar--open': sidebarOpen }">
      <div class="p-4" style="border-bottom:1px solid var(--border-subtle)">
        <RouterLink to="/" class="flex items-center gap-2 mb-2"><BrandLogo :size="26" /></RouterLink>
        <span class="admin-pill">Admin panel</span>
      </div>

      <div class="px-3 pt-3">
        <label class="search">
          <Search :size="14" aria-hidden="true" />
          <span class="sr-only">Find an admin page</span>
          <input v-model="query" type="search" placeholder="Find a page…" />
        </label>
      </div>

      <nav class="flex-1 px-3 pb-3 overflow-y-auto" aria-label="Admin">
        <section v-for="section in filteredNav" :key="section.title" class="group">
          <button type="button" class="group-head" :aria-expanded="isOpen(section.title)" @click="toggle(section.title)">
            <component :is="iconFor(section.icon)" :size="15" aria-hidden="true" />
            <span class="flex-1 text-left">{{ section.title }}</span>
            <span v-if="sectionBadge(section) > 0" class="badge">{{ sectionBadge(section) }}</span>
            <ChevronDown :size="14" class="chev" :class="{ 'chev--open': isOpen(section.title) }" aria-hidden="true" />
          </button>
          <div v-show="isOpen(section.title)" class="group-items">
            <RouterLink v-for="item in section.items" :key="item.path" :to="'/admin/' + item.path" class="side-link"
              :class="{ 'side-link--active': isActive('/admin/' + item.path) }" @click="sidebarOpen = false">
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.path === 'inbox' && unread > 0" class="badge">{{ unread > 99 ? '99+' : unread }}</span>
            </RouterLink>
          </div>
        </section>
        <p v-if="!filteredNav.length" class="text-xs px-3 py-4" style="color:var(--text-muted)">No page matches "{{ query }}".</p>
      </nav>

      <div class="p-4" style="border-top:1px solid var(--border-subtle)">
        <div class="flex items-center gap-3 mb-3">
          <div class="avatar">{{ auth.user?.name?.charAt(0)?.toUpperCase() || 'A' }}</div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold truncate" style="color:var(--text-primary)">{{ auth.user?.name || 'Admin' }}</p>
            <p class="text-xs" style="color:var(--accent-teal)">{{ roleLabels[auth.user?.role ?? ''] ?? auth.user?.role }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button @click="logout" class="signout">Sign out</button>
          <span class="hidden md:inline-flex"><NotificationBell align="up" /></span>
          <ThemeToggle :size="34" />
        </div>
      </div>
    </aside>

    <main class="admin-main"><RouterView /></main>
  </div>
</template>

<script setup lang="ts">
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ChevronDown, LayoutDashboard, List, Menu, MessageSquare, Search, ShieldCheck, Sparkles, Users, Wallet, CircleHelp } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import BrandLogo from '@/components/BrandLogo.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import NotificationBell from '@/components/NotificationBell.vue'
import { adminNav, roleLabels, type AdminNavSection } from '@/router/adminNav'
import client from '@/api/client'

const route = useRoute()
const auth = useAuthStore()
const router = useRouter()
const query = ref('')
const sidebarOpen = ref(false)
const unread = ref(0)

const ICONS: Record<string, unknown> = {
  layout: LayoutDashboard, list: List, users: Users, message: MessageSquare,
  sparkles: Sparkles, wallet: Wallet, shield: ShieldCheck,
}
const iconFor = (name: string) => ICONS[name] ?? CircleHelp

const visibleNav = computed(() =>
  adminNav
    .map((section) => ({ ...section, items: section.items.filter((i) => auth.can(i.permission)) }))
    .filter((section) => section.items.length > 0),
)

const filteredNav = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return visibleNav.value
  return visibleNav.value
    .map((s) => ({ ...s, items: s.items.filter((i) => i.label.toLowerCase().includes(q) || s.title.toLowerCase().includes(q)) }))
    .filter((s) => s.items.length > 0)
})

// Open state per group is remembered; the group of the current page is always open.
const STORE_KEY = 'aiprice_admin_groups'
const openGroups = ref<Record<string, boolean>>({})
try { openGroups.value = JSON.parse(localStorage.getItem(STORE_KEY) || '{}') } catch { /* ignore */ }
const isActive = (to: string) => route.path === to || route.path.startsWith(to + '/')
const activeGroup = computed(() => visibleNav.value.find((s) => s.items.some((i) => isActive('/admin/' + i.path)))?.title)
const isOpen = (title: string) => !!query.value.trim() || title === activeGroup.value || (openGroups.value[title] ?? title === 'Dashboard')
function toggle(title: string) {
  openGroups.value = { ...openGroups.value, [title]: !isOpen(title) }
  try { localStorage.setItem(STORE_KEY, JSON.stringify(openGroups.value)) } catch { /* ignore */ }
}
const sectionBadge = (s: AdminNavSection) => (s.items.some((i) => i.path === 'inbox') && !isOpen(s.title) ? unread.value : 0)

async function loadUnread() {
  if (!auth.can('manage_support')) return
  try {
    const { data } = await client.get('/notifications', { params: { kind: 'staff_alert', unread: 1, per_page: 5 } })
    unread.value = data.notifications?.total ?? 0
  } catch { /* offline: keep last value */ }
}
let timer: number | undefined
onMounted(() => { loadUnread(); timer = window.setInterval(loadUnread, 60000) })
onBeforeUnmount(() => window.clearInterval(timer))
watch(() => route.fullPath, () => { sidebarOpen.value = false; loadUnread() })

async function logout() { await auth.logout(); router.push('/') }
</script>

<style scoped>
.admin-shell { display:flex;min-height:100vh;background:var(--bg-base) }
.admin-sidebar { width:272px;flex-shrink:0;display:flex;flex-direction:column;background:var(--bg-surface);border-right:1px solid var(--border-subtle);position:sticky;top:0;height:100vh }
.admin-main { flex:1;min-width:0;overflow:auto;background:var(--bg-base) }
.admin-pill { font-size:11px;padding:2px 8px;border-radius:999px;font-weight:700;background:rgba(var(--accent-cyan-rgb),.1);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),.2) }
.search { display:flex;align-items:center;gap:6px;padding:7px 10px;border-radius:10px;background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-muted) }
.search input { flex:1;min-width:0;background:transparent;border:none;outline:none;font-size:13px;color:var(--text-primary) }
.search:focus-within { outline:2px solid var(--focus-ring);outline-offset:1px }
.group { margin-top:10px }
.group-head { width:100%;display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;font-size:12px;font-weight:800;letter-spacing:.02em;color:var(--text-secondary);background:none;border:none;cursor:pointer }
.group-head:hover { background:var(--surface-overlay) }
.chev { transition:transform .15s;color:var(--text-muted) }
.chev--open { transform:rotate(180deg) }
.group-items { padding:2px 0 4px 18px;border-left:1px solid var(--border-subtle);margin-left:17px }
.side-link { display:flex;align-items:center;gap:6px;padding:7px 10px;border-radius:8px;font-size:13px;font-weight:500;color:var(--text-muted);text-decoration:none;margin-bottom:1px }
.side-link:hover { background:var(--surface-overlay);color:var(--text-primary) }
.side-link--active { background:rgba(var(--accent-cyan-rgb),.1);color:var(--accent-cyan);font-weight:700 }
.badge { min-width:18px;height:18px;padding:0 5px;border-radius:999px;font-size:10px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;background:var(--danger);color:#fff }
.avatar { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;background:linear-gradient(135deg,#0567b5,#047857);color:#fff }
.signout { flex:1;padding:8px;border-radius:8px;font-size:12px;font-weight:600;background:rgba(var(--danger-rgb),.08);border:1px solid rgba(var(--danger-rgb),.15);color:var(--danger);cursor:pointer }
.admin-topbar { position:fixed;top:0;left:0;right:0;height:56px;z-index:60;display:flex;align-items:center;justify-content:space-between;padding:0 12px;background:var(--bg-surface);border-bottom:1px solid var(--border-subtle) }
.icon-btn { width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary) }
.admin-scrim { position:fixed;inset:0;z-index:69;background:rgba(4,12,24,.45) }
@media (max-width: 767px) {
  .admin-shell { padding-top:56px }
  .admin-sidebar { position:fixed;top:0;bottom:0;left:0;z-index:70;transform:translateX(-100%);transition:transform .2s;box-shadow:var(--shadow-lg) }
  .admin-sidebar--open { transform:none }
}
</style>
