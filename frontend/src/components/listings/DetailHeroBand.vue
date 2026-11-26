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
.band { background: url('/hero-coast.svg') center 60% / cover no-repeat, #061423; }
.band::before {
  content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 65%;
  background: url('/band-kyrenia.jpg') 70% 40% / cover no-repeat;
  -webkit-mask-image: linear-gradient(90deg, transparent 0%, #000 45%); mask-image: linear-gradient(90deg, transparent 0%, #000 45%); pointer-events: none;
}
.band::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(6,20,35,.15) 0%, rgba(6,20,35,.55) 100%); pointer-events: none; }
@media (max-width: 767px) { .band::before { width: 100%; opacity: .45; } }
.chip { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700;color:#bfefff;background:rgba(18,197,232,.12);border:1px solid rgba(18,197,232,.35) }
.accent { background: linear-gradient(90deg, #4fd8f5, #4fe0b0); -webkit-background-clip: text; background-clip: text; color: transparent }
</style>
