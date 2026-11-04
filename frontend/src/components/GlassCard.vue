<template>
  <div class="glass-card" :class="[glowClass, { 'glass-card--hover': hover }]">
    <slot />
  </div>
</template>
<script setup lang="ts">
const props = defineProps<{ glow?: 'teal'|'cyan'|'violet'|'none'; hover?: boolean }>()
import { computed } from "vue"
const glowClass = computed(() => {
  if (props.glow === 'none') return ""
  return { teal: "glow-teal", cyan: "glow-cyan", violet: "glow-violet" }[props.glow ?? "teal"] ?? "glow-teal"
})
</script>
<style scoped>
.glass-card {
  background: rgba(13,25,35,0.7);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid var(--border-subtle);
  border-radius: 16px;
  transition: border-color 0.25s, box-shadow 0.25s, transform 0.25s;
}
.glass-card--hover:hover {
  transform: translateY(-3px);
}
.glow-teal { border-color: rgba(var(--accent-teal-rgb),0.2); box-shadow: 0 0 30px rgba(var(--accent-teal-rgb),0.06), inset 0 0 30px rgba(var(--accent-teal-rgb),0.02); }
.glow-cyan { border-color: rgba(var(--accent-cyan-rgb),0.2); box-shadow: 0 0 30px rgba(var(--accent-cyan-rgb),0.06), inset 0 0 30px rgba(var(--accent-cyan-rgb),0.02); }
.glow-violet { border-color: rgba(var(--accent-violet-rgb),0.2); box-shadow: 0 0 30px rgba(var(--accent-violet-rgb),0.06), inset 0 0 30px rgba(var(--accent-violet-rgb),0.02); }
</style>