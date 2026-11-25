<template>
  <section class="band relative overflow-hidden px-4">
    <div class="max-w-7xl mx-auto relative z-10 pt-8 pb-28 md:pb-32">
      <span class="chip">✦ {{ $t('AI-powered market intelligence for North Cyprus') }}</span>
      <h1 class="text-3xl sm:text-4xl font-black mt-3 mb-2" style="color:#fff">{{ $t(lead) }} <span class="accent">{{ $t(accent) }}</span></h1>
      <p class="text-sm sm:text-base max-w-2xl" style="color:#cfe0f3">{{ $t(subtitle) }}</p>
    </div>
  </section>
</template>
<script setup lang="ts">
defineProps<{ lead: string; accent: string; subtitle: string }>()
</script>
<style scoped>
.band {
  background:
    linear-gradient(90deg, rgba(4,14,26,.94) 0%, rgba(5,20,36,.8) 45%, rgba(6,24,42,.35) 100%),
    url('/hero-coast.svg') center 60% / cover no-repeat, #061423;
}
.chip { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;color:#bfefff;background:rgba(18,197,232,.12);border:1px solid rgba(18,197,232,.35) }
.accent { background: linear-gradient(90deg, #4fd8f5, #4fe0b0); -webkit-background-clip: text; background-clip: text; color: transparent }
</style>
