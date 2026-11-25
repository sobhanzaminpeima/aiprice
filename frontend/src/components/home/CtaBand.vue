<template>
  <section class="px-4 pb-20 max-w-7xl mx-auto">
    <div class="cta-band rounded-3xl px-6 py-8 md:px-10 flex flex-wrap items-center justify-between gap-5">
      <div class="max-w-2xl">
        <h2 class="text-xl sm:text-2xl font-black mb-1" style="color:#fff">{{ $t('Get an AI price check or list your car, property or rental') }}</h2>
        <p class="text-sm" style="color:#cfe0f3">{{ $t('Know the real value, reach the right buyers and make smarter decisions in North Cyprus.') }}</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <RouterLink to="/cars" class="px-5 py-3 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,#12C5E8,#16C784);color:#04111d">✦ {{ $t('Get an AI price check') }}</RouterLink>
        <RouterLink :to="sellLink" class="px-5 py-3 rounded-xl text-sm font-bold" style="color:#fff;border:1px solid rgba(255,255,255,.4)">{{ $t('List your ad') }}</RouterLink>
      </div>
    </div>
  </section>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
const sellLink = computed(() => (auth.isAuthenticated ? '/dashboard/listings/new' : { name: 'register', query: { redirect: '/dashboard/listings/new' } }))
</script>
<style scoped>
.cta-band { background: linear-gradient(120deg, #0a2540 0%, #0b4a78 100%); border: 1px solid rgba(120,190,255,.25); box-shadow: 0 18px 48px rgba(2,10,22,.25); }
</style>
