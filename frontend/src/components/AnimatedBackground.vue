<template>
  <div class="animated-bg" aria-hidden="true">
    <!-- Grid overlay -->
    <div class="grid-overlay"></div>
    <!-- Floating orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <!-- Scanline -->
    <div class="scanline"></div>
  </div>
</template>

<script setup lang="ts">
</script>

<style scoped>
.animated-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
}
.grid-overlay {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(var(--accent-teal-rgb),0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(var(--accent-teal-rgb),0.035) 1px, transparent 1px);
  background-size: 52px 52px;
  mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 40%, transparent 100%);
}
.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.18;
  animation: drift 18s ease-in-out infinite alternate;
}
.orb-1 {
  width: 560px; height: 560px;
  background: radial-gradient(circle, var(--accent-teal), transparent 70%);
  top: -200px; left: -100px;
  animation-duration: 20s;
}
.orb-2 {
  width: 420px; height: 420px;
  background: radial-gradient(circle, var(--accent-cyan), transparent 70%);
  top: 20%; right: -120px;
  animation-duration: 25s;
  animation-delay: -8s;
}
.orb-3 {
  width: 320px; height: 320px;
  background: radial-gradient(circle, var(--accent-violet), transparent 70%);
  bottom: 15%; left: 30%;
  animation-duration: 30s;
  animation-delay: -15s;
}
.scanline {
  position: absolute;
  left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(var(--accent-teal-rgb),0.3), transparent);
  animation: scan 6s linear infinite;
  opacity: 0.5;
}
@keyframes drift {
  from { transform: translate(0, 0) scale(1); }
  to { transform: translate(40px, 30px) scale(1.08); }
}
@keyframes scan {
  from { top: -2px; }
  to { top: 100%; }
}
</style>