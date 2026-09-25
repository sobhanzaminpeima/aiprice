<template>
  <MainLayout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-24">
      <RouterLink to="/blog" class="text-sm" style="color:var(--text-muted)">← All articles</RouterLink>

      <div v-if="loading" class="mt-8 space-y-4">
        <div class="h-10 w-3/4 rounded animate-pulse" style="background:var(--bg-surface)"></div>
        <div class="h-64 rounded-2xl animate-pulse" style="background:var(--bg-surface)"></div>
      </div>

      <div v-else-if="notFound" class="py-24 text-center">
        <p class="text-lg font-bold mb-2" style="color:var(--text-primary)">Article not found</p>
        <RouterLink to="/blog" style="color:var(--accent-teal)">Browse the blog</RouterLink>
      </div>

      <article v-else-if="post" class="mt-8">
        <p class="text-xs font-semibold mb-3" style="color:var(--accent-teal)">{{ post.category?.name || 'Insights' }}</p>
        <h1 class="text-4xl font-black mb-4 leading-tight" style="color:var(--text-primary)">{{ post.title }}</h1>
        <p class="text-sm mb-8" style="color:var(--text-muted)">{{ post.author?.name }} · {{ fmt(post.published_at) }} · {{ readingMinutes }} min read</p>
        <img v-if="post.featured_image" :src="post.featured_image" :alt="post.title" class="w-full rounded-2xl mb-10" />
        <div class="prose-content" v-html="html"></div>
        <div v-if="post.tags?.length" class="flex flex-wrap gap-2 mt-10">
          <span v-for="t in post.tags" :key="t" class="text-xs px-3 py-1 rounded-full" style="background:var(--surface-overlay);color:var(--text-secondary)">#{{ t }}</span>
        </div>
      </article>

      <section v-if="related.length" class="mt-16 pt-10" style="border-top:1px solid var(--border-subtle)">
        <h2 class="font-bold mb-5" style="color:var(--text-primary)">Keep reading</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <RouterLink v-for="r in related" :key="r.id" :to="`/blog/${r.slug}`" class="p-4 rounded-xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <p class="text-sm font-semibold mb-1" style="color:var(--text-primary)">{{ r.title }}</p>
            <p class="text-xs line-clamp-2" style="color:var(--text-muted)">{{ r.excerpt }}</p>
          </RouterLink>
        </div>
      </section>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import client from '@/api/client'
import { renderSafeMarkdown } from '@/utils/safeMarkdown'
import { usePageMeta } from '@/composables/usePageMeta'

const route = useRoute()
const post = ref<any>(null)
const related = ref<any[]>([])
const loading = ref(true)
const notFound = ref(false)

const html = computed(() => renderSafeMarkdown(post.value?.content ?? ''))
const readingMinutes = computed(() => Math.max(1, Math.round((post.value?.content ?? '').split(/\s+/).length / 220)))

usePageMeta(() => post.value && {
  title: post.value.seo_title || post.value.title,
  description: post.value.seo_description || post.value.excerpt,
  image: post.value.featured_image,
  type: 'article',
})

async function load(slug: string) {
  loading.value = true
  notFound.value = false
  try {
    const r = await client.get(`/blog/${slug}`)
    post.value = r.data.post
    related.value = r.data.related
  } catch {
    notFound.value = true
    post.value = null
  } finally {
    loading.value = false
  }
}

function fmt(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }) }

watch(() => route.params.slug, (slug) => slug && load(String(slug)), { immediate: true })
</script>

<style scoped>
.prose-content :deep(h2) { font-size: 1.5rem; font-weight: 800; margin: 2rem 0 .75rem; color: var(--text-primary); }
.prose-content :deep(h3) { font-size: 1.2rem; font-weight: 700; margin: 1.5rem 0 .5rem; color: var(--text-primary); }
.prose-content :deep(p), .prose-content :deep(li) { font-size: 1.02rem; line-height: 1.8; color: var(--text-secondary); }
.prose-content :deep(p) { margin: 1rem 0; }
.prose-content :deep(ul) { list-style: disc; padding-left: 1.5rem; margin: 1rem 0; }
.prose-content :deep(ol) { list-style: decimal; padding-left: 1.5rem; margin: 1rem 0; }
.prose-content :deep(a) { color: var(--accent-teal); text-decoration: underline; }
.prose-content :deep(code) { font-size: .85rem; padding: .1rem .35rem; border-radius: 4px; background: var(--surface-overlay); }
.prose-content :deep(blockquote) { border-left: 3px solid var(--accent-teal); padding-left: 1rem; margin: 1.25rem 0; color: var(--text-muted); font-style: italic; }
</style>
