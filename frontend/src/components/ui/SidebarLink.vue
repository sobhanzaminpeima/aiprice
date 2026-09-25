<template>
  <RouterLink
    :to="to"
    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all"
    :class="isActive
      ? 'text-[#6fffd2]'
      : 'text-[var(--text-muted)] hover:text-[var(--text-primary)]'"
    :style="isActive
      ? 'background:rgba(111,255,210,0.08);border:1px solid rgba(111,255,210,0.15)'
      : 'border:1px solid transparent'"
  >
    <span class="text-base w-5 text-center shrink-0">{{ iconEmoji }}</span>
    <slot />
  </RouterLink>
</template>

<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { computed } from 'vue'

const props = defineProps<{ to: string; icon: string }>()
const route = useRoute()
const isActive = computed(() => route.path === props.to || (props.to !== '/dashboard' && props.to !== '/admin' && route.path.startsWith(props.to + '/')))

const iconMap: Record<string, string> = {
  grid: '⊞', list: '≡', plus: '+', message: '✉', chart: '◉', heart: '♡',
  star: '★', shield: '🛡', user: '◎', users: '◎◎', car: '🚗', cpu: '⬡',
  globe: '◈', 'credit-card': '▣', help: '?', settings: '⚙', 'bar-chart': '▦',
}
const iconEmoji = computed(() => iconMap[props.icon] || '·')
</script>
