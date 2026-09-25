<template>
  <div class="p-8">
    <div class="mb-8 flex items-start justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-3xl font-black mb-1" style="color:#f0f6fc">Instagram <span class="gradient-text">Auto-Post</span></h1>
        <p class="text-sm" style="color:#6e7f96">AI picks the best photo, designs a branded post, writes a viral caption — you approve, then publish.</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="testConn" :disabled="testing" class="px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-60"
          style="background:rgba(255,255,255,0.05);color:#c9d3e0;border:1px solid rgba(255,255,255,0.1)">
          {{ testing ? 'Testing…' : 'Test connection' }}
        </button>
        <button @click="generateBatch" :disabled="generating" class="px-4 py-2.5 rounded-xl text-sm font-bold disabled:opacity-60"
          style="background:linear-gradient(135deg,#00BFFF,#00E6B0);color:#05070b">
          {{ generating ? 'Generating…' : 'Generate drafts' }}
        </button>
      </div>
    </div>

    <div v-if="connMsg" class="mb-6 px-4 py-3 rounded-xl text-sm" :style="connOk
      ? 'background:rgba(0,230,176,0.1);border:1px solid rgba(0,230,176,0.25);color:#00E6B0'
      : 'background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#ef4444'">
      {{ connMsg }}
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-2 mb-6">
      <button v-for="f in filters" :key="f.value" @click="status = f.value; load()"
        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition"
        :style="status === f.value
          ? 'background:rgba(0,191,255,0.15);color:#00BFFF;border:1px solid rgba(0,191,255,0.3)'
          : 'background:rgba(255,255,255,0.04);color:#6e7f96;border:1px solid rgba(255,255,255,0.07)'">
        {{ f.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-20" style="color:#6e7f96">Loading…</div>
    <div v-else-if="posts.length === 0" class="text-center py-20 rounded-2xl" style="background:#0d1117;border:1px solid rgba(255,255,255,0.07);color:#6e7f96">
      No posts yet. Click <span style="color:#00E6B0">Generate drafts</span> to create posts from your active listings.
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
      <div v-for="post in posts" :key="post.id" class="rounded-2xl overflow-hidden flex flex-col" style="background:#0d1117;border:1px solid rgba(255,255,255,0.07)">
        <div class="relative" style="aspect-ratio:4/5;background:#05070b">
          <img v-if="post.image_url" :src="post.image_url" :alt="post.listing?.title" class="w-full h-full object-cover" loading="lazy" />
          <div v-else class="w-full h-full flex items-center justify-center text-xs" style="color:#3a4554">No image</div>
          <span class="absolute top-3 left-3 text-xs px-2.5 py-1 rounded-full font-semibold capitalize" :style="badgeStyle(post.status)">{{ post.status }}</span>
        </div>

        <div class="p-4 flex flex-col gap-3 flex-1">
          <p class="text-sm font-bold truncate" style="color:#f0f6fc">{{ post.listing?.title || 'Listing' }}</p>
          <textarea v-model="post.caption" rows="4" class="w-full text-xs rounded-lg p-2.5 resize-none outline-none"
            style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#c9d3e0"></textarea>
          <div class="flex flex-wrap gap-1">
            <span v-for="(h, i) in post.hashtags" :key="i" class="text-xs px-2 py-0.5 rounded" style="background:rgba(0,191,255,0.1);color:#00BFFF">{{ h }}</span>
          </div>

          <div class="flex items-center gap-2 mt-auto pt-2">
            <button v-if="post.status !== 'published'" @click="save(post)" class="px-3 py-2 rounded-lg text-xs font-semibold"
              style="background:rgba(255,255,255,0.05);color:#c9d3e0;border:1px solid rgba(255,255,255,0.1)">Save</button>
            <button v-if="post.status !== 'published'" @click="publish(post)" :disabled="post._busy" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold disabled:opacity-60"
              style="background:linear-gradient(135deg,#00BFFF,#00E6B0);color:#05070b">
              {{ post._busy ? 'Publishing…' : 'Approve & Publish' }}
            </button>
            <a v-else :href="post.ig_permalink || '#'" target="_blank" class="flex-1 text-center px-3 py-2 rounded-lg text-xs font-bold"
              style="background:rgba(0,230,176,0.12);color:#00E6B0">View on Instagram ↗</a>
            <button @click="remove(post)" class="px-2.5 py-2 rounded-lg text-xs" style="background:rgba(239,68,68,0.08);color:#ef4444" title="Delete">✕</button>
          </div>
          <p v-if="post.error" class="text-xs" style="color:#ef4444">{{ post.error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import client from '@/api/client'

interface Post {
  id: string; status: string; image_url?: string; caption: string; hashtags: string[]
  error?: string; ig_permalink?: string; listing?: { title?: string; type?: string }; _busy?: boolean
}

const posts = ref<Post[]>([])
const loading = ref(false)
const generating = ref(false)
const testing = ref(false)
const status = ref('')
const connMsg = ref('')
const connOk = ref(false)

const filters = [
  { label: 'All', value: '' },
  { label: 'Drafts', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'Failed', value: 'failed' },
]

function badgeStyle(s: string) {
  const map: Record<string, string> = {
    draft: 'background:rgba(255,255,255,0.12);color:#c9d3e0',
    published: 'background:rgba(0,230,176,0.85);color:#05241c',
    publishing: 'background:rgba(0,191,255,0.85);color:#05070b',
    failed: 'background:rgba(239,68,68,0.85);color:#fff',
  }
  return map[s] || map.draft
}

async function load() {
  loading.value = true
  try {
    const { data } = await client.get('/admin/social/posts', { params: status.value ? { status: status.value } : {} })
    posts.value = (data.data || data).map((p: Post) => ({ ...p, hashtags: p.hashtags || [] }))
  } catch (e) { /* handled globally */ } finally { loading.value = false }
}

async function generateBatch() {
  generating.value = true
  try {
    const { data } = await client.post('/admin/social/generate-batch', { limit: 12 })
    connOk.value = true; connMsg.value = data.message
    await load()
  } catch (e: any) {
    connOk.value = false; connMsg.value = e.response?.data?.message || 'Generation failed.'
  } finally { generating.value = false }
}

async function save(post: Post) {
  await client.put(`/admin/social/posts/${post.id}`, { caption: post.caption, hashtags: post.hashtags })
}

async function publish(post: Post) {
  post._busy = true
  try {
    await client.put(`/admin/social/posts/${post.id}`, { caption: post.caption, hashtags: post.hashtags })
    const { data } = await client.post(`/admin/social/posts/${post.id}/publish`)
    Object.assign(post, data)
  } catch (e: any) {
    post.error = e.response?.data?.message || 'Publish failed.'
  } finally { post._busy = false }
}

async function remove(post: Post) {
  await client.delete(`/admin/social/posts/${post.id}`)
  posts.value = posts.value.filter(p => p.id !== post.id)
}

async function testConn() {
  testing.value = true
  try {
    const { data } = await client.get('/admin/social/test')
    const ig = data.instagram, cl = data.cloudinary
    if (ig.ok) {
      connOk.value = true
      connMsg.value = `Instagram OK — @${ig.username} (${ig.followers ?? 0} followers). Cloudinary: ${cl.ok ? 'OK' : 'not configured'}.`
    } else {
      connOk.value = false
      connMsg.value = `Instagram not connected: ${ig.message}`
    }
  } catch (e: any) {
    connOk.value = false; connMsg.value = e.response?.data?.message || 'Test failed.'
  } finally { testing.value = false }
}

onMounted(load)
</script>
