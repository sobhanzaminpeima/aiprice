<template>
  <MainLayout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-24">
      <h1 class="text-4xl font-black mb-4" style="color:var(--text-primary)">Frequently asked <span class="gradient-text">questions</span></h1>
      <p class="mb-10" style="color:var(--text-muted)">Can't find your answer? <RouterLink :to="auth.isAuthenticated ? '/dashboard/support' : '/contact'" style="color:var(--accent-teal)">Contact support</RouterLink>.</p>

      <div v-if="loading" class="space-y-3"><div v-for="i in 5" :key="i" class="h-14 rounded-xl animate-pulse" style="background:var(--bg-surface)"></div></div>
      <p v-else-if="!groups.length" style="color:var(--text-muted)">No FAQs published yet.</p>

      <section v-for="g in groups" :key="g.name" class="mb-10">
        <h2 v-if="groups.length > 1" class="text-sm font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted)">{{ g.name }}</h2>
        <details v-for="f in g.items" :key="f.id" class="group mb-2 rounded-xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <summary class="cursor-pointer list-none px-5 py-4 flex justify-between items-center gap-4 font-semibold text-sm" style="color:var(--text-primary)">
            {{ f.question }}<span class="transition group-open:rotate-45 text-lg" style="color:var(--accent-teal)">+</span>
          </summary>
          <p class="px-5 pb-5 text-sm whitespace-pre-line leading-relaxed" style="color:var(--text-secondary)">{{ f.answer }}</p>
        </details>
      </section>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import client from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { usePageMeta } from '@/composables/usePageMeta'

const auth = useAuthStore()
const faqs = ref<any[]>([])
const loading = ref(true)

usePageMeta(() => ({ title: 'FAQ', description: 'Answers to common questions about buying, selling and AI pricing on AIPrice.app.' }))

const groups = computed(() => {
  const map = new Map<string, any[]>()
  for (const f of faqs.value) {
    const key = f.category || 'General'
    map.set(key, [...(map.get(key) ?? []), f])
  }
  return [...map].map(([name, items]) => ({ name, items }))
})

onMounted(async () => {
  try { faqs.value = (await client.get('/faqs')).data } finally { loading.value = false }
})
</script>
