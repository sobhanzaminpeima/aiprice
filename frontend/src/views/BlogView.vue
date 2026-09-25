<template>
  <MainLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
      <h1 class="text-4xl font-black mb-4" style="color:var(--text-primary)">Market Intelligence <span class="gradient-text">Blog</span></h1>
      <p class="mb-8" style="color:var(--text-muted)">Insights and analysis from the AIPrice.app team.</p>

      <div v-if="categories.length" class="flex flex-wrap gap-2 mb-10">
        <button @click="setCategory('')" class="px-4 py-1.5 rounded-full text-xs font-semibold" :style="!category ? activeChip : chip">All</button>
        <button v-for="c in categories" :key="c.id" @click="setCategory(c.slug)" class="px-4 py-1.5 rounded-full text-xs font-semibold" :style="category === c.slug ? activeChip : chip">{{ c.name }} <span class="opacity-60">{{ c.posts_count }}</span></button>
      </div>

      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div v-for="i in 3" :key="i" class="h-80 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>
      <p v-else-if="error" class="text-sm" style="color:#ef4444">{{ error }}</p>
      <p v-else-if="!posts.length" class="py-20 text-center" style="color:var(--text-muted)">No articles published yet — check back soon.</p>

      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <RouterLink v-for="post in posts" :key="post.id" :to="`/blog/${post.slug}`" class="rounded-2xl overflow-hidden block hover:-translate-y-0.5 transition" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <img v-if="post.featured_image" :src="post.featured_image" :alt="post.title" loading="lazy" class="h-48 w-full object-cover" />
          <div v-else class="h-48 flex items-center justify-center text-4xl" style="background:linear-gradient(135deg,rgba(111,255,210,0.12),rgba(86,216,255,0.12))">📊</div>
          <div class="p-6">
            <span class="text-xs font-semibold" style="color:var(--accent-teal)">{{ post.category?.name || 'Insights' }}</span>
            <h2 class="font-bold mt-2 mb-3" style="color:var(--text-primary)">{{ post.title }}</h2>
            <p class="text-sm line-clamp-3" style="color:var(--text-secondary)">{{ post.excerpt }}</p>
            <p class="text-xs mt-4" style="color:var(--text-muted)">{{ fmt(post.published_at) }}</p>
          </div>
        </RouterLink>
      </div>

      <div v-if="lastPage > 1" class="flex justify-center gap-3 mt-10">
        <button :disabled="page <= 1" @click="load(page - 1)" class="px-4 py-2 rounded-lg text-sm disabled:opacity-40" :style="chip">Newer</button>
        <button :disabled="page >= lastPage" @click="load(page + 1)" class="px-4 py-2 rounded-lg text-sm disabled:opacity-40" :style="chip">Older</button>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'

const chip = 'background:var(--surface-overlay);color:var(--text-secondary);border:1px solid var(--border-subtle)'
const activeChip = 'background:rgba(111,255,210,0.1);color:var(--accent-teal);border:1px solid rgba(111,255,210,0.25)'

const posts = ref<any[]>([])
const categories = ref<any[]>([])
const category = ref('')
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref('')

async function load(p = 1) {
  loading.value = true
  try {
    const r = await client.get('/blog', { params: { page: p, category: category.value || undefined } })
    posts.value = r.data.data
    page.value = r.data.current_page
    lastPage.value = r.data.last_page
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load articles.')
  } finally {
    loading.value = false
  }
}

function setCategory(slug: string) { category.value = slug; load(1) }
function fmt(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) }

onMounted(() => {
  load()
  client.get('/blog/categories').then((r) => (categories.value = r.data.filter((c: any) => c.posts_count > 0))).catch(() => {})
})
</script>
