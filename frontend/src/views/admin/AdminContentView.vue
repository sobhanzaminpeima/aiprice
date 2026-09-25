<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-3xl font-black" style="color:var(--text-primary)">Content <span class="gradient-text">Management</span></h1>
      <button class="btn-primary px-5 py-2.5 text-sm">+ New Post</button>
    </div>

    <!-- Content tabs -->
    <div class="flex gap-2 mb-6">
      <button v-for="t in tabs" :key="t" @click="activeTab = t"
        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all"
        :style="activeTab===t ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.2)' : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid transparent'">
        {{ t }}
      </button>
    </div>

    <!-- Blog Posts -->
    <div v-if="activeTab === 'Blog Posts'" class="space-y-3">
      <div v-for="post in blogPosts" :key="post.id" class="flex items-center gap-4 p-4 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="w-16 h-12 rounded-xl overflow-hidden shrink-0">
          <img :src="post.img" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ post.title }}</p>
          <p class="text-xs" style="color:var(--text-muted)">{{ post.date }} · {{ post.views }} views</p>
        </div>
        <span class="text-xs px-2 py-1 rounded-full font-medium" :style="post.published ? 'background:rgba(111,255,210,0.1);color:var(--accent-teal)' : 'background:rgba(251,191,36,0.1);color:#fbbf24'">
          {{ post.published ? 'Published' : 'Draft' }}
        </span>
        <div class="flex gap-2">
          <button class="text-xs px-3 py-1.5 rounded-lg" style="background:rgba(86,216,255,0.08);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.15)">Edit</button>
          <button class="text-xs px-3 py-1.5 rounded-lg" style="background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.15)">Delete</button>
        </div>
      </div>
    </div>

    <!-- Banners -->
    <div v-if="activeTab === 'Banners'" class="space-y-3">
      <div v-for="b in banners" :key="b.id" class="flex items-center gap-4 p-4 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="w-24 h-12 rounded-xl flex items-center justify-center text-xs font-bold" :style="`background:rgba(${b.rgb},0.1);color:rgb(${b.rgb})`">
          {{ b.type }}
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ b.title }}</p>
          <p class="text-xs" style="color:var(--text-muted)">Position: {{ b.position }} · Clicks: {{ b.clicks }}</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" :checked="b.active" class="sr-only peer" />
          <div class="w-9 h-5 rounded-full peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:transition-all" :style="b.active ? 'background:#6fffd2' : 'background:var(--border-strong)'"></div>
        </label>
      </div>
    </div>

    <!-- Pages -->
    <div v-if="activeTab === 'Pages'" class="space-y-3">
      <div v-for="pg in pages" :key="pg.slug" class="flex items-center gap-4 p-4 rounded-2xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" :style="'background:var(--surface-overlay)'">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" :stroke="pg.color" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="pg.iconPath"/></svg>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ pg.title }}</p>
          <p class="text-xs font-mono" style="color:var(--text-muted)">/{{ pg.slug }}</p>
        </div>
        <button class="text-xs px-3 py-1.5 rounded-lg" style="background:rgba(86,216,255,0.08);color:var(--accent-cyan);border:1px solid rgba(86,216,255,0.15)">Edit</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
const tabs = ['Blog Posts', 'Banners', 'Pages']
const activeTab = ref('Blog Posts')
const blogPosts = [
  { id: 1, title: 'How AI is Changing North Cyprus Real Estate in 2026', date: '2026-06-15', views: 1284, published: true, img: 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=200&q=70' },
  { id: 2, title: 'Best Areas to Buy Property in Kyrenia: 2026 Guide', date: '2026-06-08', views: 943, published: true, img: 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=200&q=70' },
  { id: 3, title: 'Hybrid Cars Coming to North Cyprus: What You Need to Know', date: '2026-06-01', views: 621, published: true, img: 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?w=200&q=70' },
  { id: 4, title: 'Investment Guide: Long Beach Apartments ROI Analysis', date: '2026-05-20', views: 0, published: false, img: 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=200&q=70' },
]
const banners = [
  { id: 1, title: 'Summer 2026 — List Free!', type: 'Hero', position: 'Homepage Top', clicks: 284, active: true, rgb: '111,255,210' },
  { id: 2, title: 'Dealer Plan Special Offer', type: 'Sidebar', position: 'Listing Pages', clicks: 93, active: true, rgb: '86,216,255' },
  { id: 3, title: 'AI Price Check is Live', type: 'Popup', position: 'Cars Page', clicks: 421, active: false, rgb: '167,139,250' },
]
const pages = [
  { iconPath: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#6fffd2', title: 'About AIPrice.app', slug: 'about' },
  { iconPath: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', color: '#56d8ff', title: 'Contact Us', slug: 'contact' },
  { iconPath: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', color: '#a78bfa', title: 'Privacy Policy', slug: 'privacy' },
  { iconPath: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: '#fbbf24', title: 'Terms of Service', slug: 'terms' },
  { iconPath: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', color: 'var(--text-muted)', title: 'Cookie Policy', slug: 'cookies' },
]
</script>
