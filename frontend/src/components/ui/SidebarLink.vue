<template>
  <RouterLink
    :to="to"
    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all"
    :class="isActive
      ? 'text-[var(--accent-teal)]'
      : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'"
    :style="isActive
      ? 'background:rgba(var(--accent-teal-rgb),0.08);border:1px solid rgba(var(--accent-teal-rgb),0.15)'
      : 'border:1px solid transparent'"
  >
    <AppIcon :name="iconName" :size="18" class="w-5 shrink-0" />
    <slot />
  </RouterLink>
</template>

<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps<{ to: string; icon: string }>()
const route = useRoute()
const isActive = computed(() => route.path === props.to || (props.to !== '/dashboard' && props.to !== '/admin' && route.path.startsWith(props.to + '/')))

const iconMap: Record<string, string> = {
  grid: 'clipboard', list: 'list', plus: 'plus', message: 'message', chart: 'chart', heart: 'heart',
  star: 'star', shield: 'shield', user: 'user', users: 'users', car: 'car', cpu: 'cpu',
  globe: 'globe', 'credit-card': 'creditCard', help: 'help', settings: 'settings', 'bar-chart': 'chart',
}
const iconName = computed(() => iconMap[props.icon] || 'help')
</script>
