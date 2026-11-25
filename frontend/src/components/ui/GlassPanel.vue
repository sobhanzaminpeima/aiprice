<template>
  <div class="glass-panel" :class="{ 'glass-panel--light': light }"><slot /></div>
</template>
<script setup lang="ts">
defineProps<{ light?: boolean }>()
</script>
<style scoped>
.glass-panel { background: linear-gradient(160deg, rgba(14,42,72,.72), rgba(8,24,39,.78)); border: 1px solid rgba(120,190,255,.22); border-radius: 20px; backdrop-filter: blur(14px); box-shadow: 0 18px 48px rgba(2,10,22,.35); color: #e8f1fb; }
.glass-panel--light { background: var(--bg-card); border: 1px solid var(--border-subtle); box-shadow: 0 1px 2px rgba(8,24,39,.04), 0 10px 30px rgba(8,24,39,.07); color: var(--text-primary); }
</style>
