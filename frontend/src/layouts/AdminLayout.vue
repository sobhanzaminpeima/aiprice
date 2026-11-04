<template>
  <div class='flex min-h-screen' style='background:var(--bg-base)'>
    <aside class='w-64 shrink-0 flex flex-col' style='background:var(--bg-surface);border-right:1px solid rgba(var(--accent-teal-rgb),0.1)'>
      <div class='p-5' style='border-bottom:1px solid var(--border-subtle)'>
        <RouterLink to='/' class='flex items-center gap-2 mb-2'>
          <BrandLogo :size="26" />
        </RouterLink>
        <span class='text-xs px-2 py-0.5 rounded-full font-semibold' style='background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)'>Admin Panel</span>
      </div>
      <nav class='flex-1 p-3 overflow-y-auto'>
        <template v-for='section in visibleNav' :key='section.title'>
          <p class='nav-section'>{{ section.title }}</p>
          <SideLink v-for='item in section.items' :key='item.path' :to="'/admin/' + item.path">{{ item.label }}</SideLink>
        </template>
      </nav>
      <div class='p-4' style='border-top:1px solid var(--border-subtle)'>
        <div class='flex items-center gap-3 mb-3'>
          <div class='w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0' style='background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)'>
            {{ auth.user?.name?.charAt(0)?.toUpperCase() || 'A' }}
          </div>
          <div class='flex-1 min-w-0'>
            <p class='text-xs font-semibold truncate' style='color:var(--text-primary)'>{{ auth.user?.name || 'Admin' }}</p>
            <p class='text-xs' style='color:var(--accent-teal)'>{{ roleLabels[auth.user?.role ?? ''] ?? auth.user?.role }}</p>
          </div>
        </div>
        <div class='flex items-center gap-2'>
          <button @click='logout' class='flex-1 py-2 rounded-lg text-xs font-medium' style='background:rgba(var(--danger-rgb),0.08);border:1px solid rgba(var(--danger-rgb),0.15);color:var(--danger)'>Sign Out</button>
          <NotificationBell align="up" />
          <ThemeToggle :size="34" />
        </div>
      </div>
    </aside>
    <main class='flex-1 overflow-auto' style='background:var(--bg-base)'><RouterView /></main>
  </div>
</template>
<script setup lang='ts'>
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { h, defineComponent, computed } from 'vue'
import BrandLogo from '@/components/BrandLogo.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { adminNav, roleLabels } from '@/router/adminNav'
import NotificationBell from '@/components/NotificationBell.vue'

const route = useRoute()
const auth = useAuthStore()
const router = useRouter()

const SideLink = defineComponent({
  props: { to: { type: String, required: true } },
  setup(props, { slots }) {
    const isActive = computed(() => {
      const p = route.path
      return p === props.to || (p.startsWith(props.to + '/') && props.to !== '/admin')
    })
    return () => h(RouterLink, {
      to: props.to,
      class: 'flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-all mb-0.5 w-full',
      style: isActive.value
        ? 'background:rgba(var(--accent-teal-rgb),0.08);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.15)'
        : 'color:var(--text-muted);border:1px solid transparent'
    }, slots.default)
  }
})

const visibleNav = computed(() =>
  adminNav
    .map((section) => ({ ...section, items: section.items.filter((i) => auth.can(i.permission)) }))
    .filter((section) => section.items.length > 0)
)

async function logout() { await auth.logout(); router.push('/') }
</script>
<style scoped>
.nav-section { font-size:10px;font-weight:700;letter-spacing:0.1em;color:var(--text-muted);padding:16px 12px 6px;text-transform:uppercase; }
</style>