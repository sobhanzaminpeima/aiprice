<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
      <h1 class="text-4xl font-black text-white mb-4">Market Intelligence <span class="gradient-text">Blog</span></h1>
      <p class="text-slate-400 mb-12">Insights, analysis, and AI-powered market intelligence from the AIPrice.app team.</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="post in placeholderPosts" :key="post.title" class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
          <div class="h-48 bg-gradient-to-br from-sky-500/20 to-violet-600/20 flex items-center justify-center text-4xl">{{ post.icon }}</div>
          <div class="p-6">
            <span class="text-xs text-sky-400 font-semibold">{{ post.category }}</span>
            <h3 class="font-bold text-white mt-2 mb-3">{{ post.title }}</h3>
            <p class="text-sm text-slate-400">{{ post.excerpt }}</p>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
<script setup lang="ts">
import MainLayout from '@/layouts/MainLayout.vue'
const placeholderPosts = [
  { icon: '🚗', category: 'Cars', title: 'How AI is Changing Used Car Pricing in 2026', excerpt: 'Our AI models analyze thousands of comparable sales to give you the most accurate market price.' },
  { icon: '🏠', category: 'Property', title: 'Top 10 Investment Properties Identified by AI', excerpt: 'Using our Investment Score™ to find hidden gems before the market catches up.' },
  { icon: '📊', category: 'Market', title: 'Q2 2026 Market Intelligence Report', excerpt: 'Comprehensive analysis of car and property market trends powered by the AIPrice.app engine.' },
]
</script>