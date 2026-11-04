<template>
  <div class="p-8">
    <div class="mb-6">
      <h1 class="text-3xl font-black mb-1" style="color:var(--text-primary)">Content <span class="gradient-text">Management</span></h1>
      <p class="text-sm" style="color:var(--text-muted)">Blog posts and FAQs shown on the public site.</p>
    </div>

    <div class="flex gap-2 mb-6">
      <button v-for="t in tabs" :key="t" @click="tab = t; editing = null" class="px-4 py-2 rounded-xl text-xs font-semibold" :style="tab === t ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal);border:1px solid rgba(var(--accent-teal-rgb),0.2)' : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid transparent'">{{ t }}</button>
    </div>

    <div v-if="error" class="rounded-2xl p-4 mb-4 text-sm" style="background:rgba(var(--danger-rgb),0.06);border:1px solid rgba(var(--danger-rgb),0.2);color:var(--danger)">{{ error }}</div>

    <!-- ============ POSTS ============ -->
    <template v-if="tab === 'Blog posts'">
      <div v-if="editing" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <form @submit.prevent="savePost" class="rounded-2xl p-6 space-y-4" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <input v-model="editing.title" required maxlength="200" placeholder="Title" class="w-full px-4 py-3 rounded-xl text-lg font-bold outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <div class="grid grid-cols-2 gap-3">
            <select v-model="editing.category_id" class="px-3 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option :value="null">No category</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <input v-model="editing.slug" placeholder="url-slug (auto)" class="px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          </div>
          <textarea v-model="editing.excerpt" rows="2" maxlength="500" placeholder="Excerpt — shown on the blog list and in search results" class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
          <textarea v-model="editing.content" required rows="16" placeholder="Write in Markdown: ## Heading, **bold**, - list, [link](https://…)" class="w-full px-3 py-2 rounded-xl text-sm font-mono outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
          <input v-model="editing.featured_image" type="url" placeholder="Featured image URL (https://…)" class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <input v-model="tagsText" placeholder="Tags, comma separated" class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <details class="text-sm" style="color:var(--text-secondary)">
            <summary class="cursor-pointer text-xs font-semibold" style="color:var(--text-muted)">SEO</summary>
            <div class="space-y-2 mt-2">
              <input v-model="editing.seo_title" maxlength="70" placeholder="SEO title (≤70 chars)" class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
              <textarea v-model="editing.seo_description" maxlength="160" rows="2" placeholder="Meta description (≤160 chars)" class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
            </div>
          </details>
          <div class="flex flex-wrap items-center gap-3">
            <select v-model="editing.status" class="px-3 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="scheduled">Scheduled</option>
            </select>
            <input v-if="editing.status === 'scheduled'" v-model="editing.published_at" type="datetime-local" required class="px-3 py-2 rounded-xl text-sm" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
            <button type="button" @click="editing = null" class="ml-auto px-4 py-2 text-sm" style="color:var(--text-muted)">Cancel</button>
            <button type="submit" :disabled="busy" class="px-5 py-2 rounded-xl text-sm font-bold disabled:opacity-50" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ busy ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
        <article class="rounded-2xl p-6 prose-content" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <p class="text-xs mb-3" style="color:var(--text-muted)">Preview</p>
          <h1 class="text-2xl font-black mb-3" style="color:var(--text-primary)">{{ editing.title || 'Untitled' }}</h1>
          <div v-html="preview"></div>
        </article>
      </div>

      <div v-else>
        <div class="flex justify-between mb-4">
          <input v-model="postSearch" @keyup.enter="loadPosts" placeholder="Search posts" class="px-3 py-2 rounded-lg text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
          <button @click="newPost" class="px-4 py-2 rounded-xl text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">New post</button>
        </div>
        <p v-if="!posts.length" class="text-sm py-12 text-center rounded-2xl" style="background:var(--bg-surface);color:var(--text-muted)">No posts yet.</p>
        <div v-else class="space-y-2">
          <div v-for="p in posts" :key="p.id" class="flex items-center gap-3 p-4 rounded-xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ p.title }}</p>
              <p class="text-xs" style="color:var(--text-muted)">{{ p.category?.name || 'Uncategorised' }} · {{ p.view_count }} views · updated {{ fmt(p.updated_at) }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full capitalize" :style="p.status === 'published' ? 'background:rgba(var(--accent-teal-rgb),0.1);color:var(--accent-teal)' : p.status === 'scheduled' ? 'background:rgba(var(--accent-cyan-rgb),0.1);color:var(--accent-cyan)' : 'background:var(--surface-overlay);color:var(--text-muted)'">{{ p.status }}</span>
            <a v-if="p.status === 'published'" :href="`/blog/${p.slug}`" target="_blank" class="text-xs" style="color:var(--text-muted)">View</a>
            <button @click="editPost(p.id)" class="text-xs" style="color:var(--accent-teal)">Edit</button>
            <button @click="deletePost(p)" class="text-xs" style="color:var(--danger)">Delete</button>
          </div>
        </div>
      </div>
    </template>

    <!-- ============ CATEGORIES ============ -->
    <template v-else-if="tab === 'Categories'">
      <form @submit.prevent="addCategory" class="flex gap-2 mb-4">
        <input v-model="newCategory" required placeholder="New category name" class="px-3 py-2 rounded-lg text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-subtle);color:var(--text-primary)" />
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">Add</button>
      </form>
      <div class="space-y-2">
        <div v-for="c in categories" :key="c.id" class="flex items-center gap-3 p-3 rounded-xl" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
          <p class="flex-1 text-sm" style="color:var(--text-primary)">{{ c.name }} <span class="text-xs" style="color:var(--text-muted)">/{{ c.slug }} · {{ c.posts_count }} posts</span></p>
          <button @click="deleteCategory(c)" :disabled="c.posts_count > 0" class="text-xs disabled:opacity-30" style="color:var(--danger)" :title="c.posts_count > 0 ? 'Has posts' : ''">Delete</button>
        </div>
      </div>
    </template>

    <!-- ============ FAQ ============ -->
    <template v-else>
      <form @submit.prevent="saveFaq" class="rounded-2xl p-5 mb-4 space-y-3" style="background:var(--bg-surface);border:1px solid var(--border-subtle)">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <input v-model="faqForm.question" required placeholder="Question" class="md:col-span-3 px-3 py-2 rounded-lg text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
          <input v-model="faqForm.category" placeholder="Category" class="px-3 py-2 rounded-lg text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)" />
        </div>
        <textarea v-model="faqForm.answer" required rows="3" placeholder="Answer" class="w-full px-3 py-2 rounded-lg text-sm outline-none" style="background:var(--surface-overlay);border:1px solid var(--border-strong);color:var(--text-primary)"></textarea>
        <div class="flex justify-end gap-2">
          <button v-if="faqForm.id" type="button" @click="resetFaq" class="px-3 py-2 text-sm" style="color:var(--text-muted)">Cancel</button>
          <button type="submit" class="px-4 py-2 rounded-lg text-sm font-bold" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent)">{{ faqForm.id ? 'Update' : 'Add FAQ' }}</button>
        </div>
      </form>
      <div class="space-y-2">
        <div v-for="f in faqs" :key="f.id" class="p-4 rounded-xl" :style="`background:var(--bg-surface);border:1px solid var(--border-subtle);${f.is_active ? '' : 'opacity:.5'}`">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ f.question }} <span v-if="f.category" class="text-xs font-normal" style="color:var(--text-muted)">· {{ f.category }}</span></p>
              <p class="text-xs mt-1" style="color:var(--text-secondary)">{{ f.answer }}</p>
            </div>
            <button @click="toggleFaq(f)" class="text-xs" style="color:var(--text-muted)">{{ f.is_active ? 'Hide' : 'Show' }}</button>
            <button @click="faqForm = { ...f }" class="text-xs" style="color:var(--accent-teal)">Edit</button>
            <button @click="deleteFaq(f)" class="text-xs" style="color:var(--danger)">Delete</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import client from '@/api/client'
import { apiErrorMessage } from '@/api/errors'
import { renderSafeMarkdown } from '@/utils/safeMarkdown'

const tabs = ['Blog posts', 'Categories', 'FAQ'] as const
const tab = ref<(typeof tabs)[number]>('Blog posts')
const error = ref('')
const busy = ref(false)

const posts = ref<any[]>([])
const postSearch = ref('')
const categories = ref<any[]>([])
const newCategory = ref('')
const editing = ref<any>(null)
const tagsText = ref('')
const faqs = ref<any[]>([])
const faqForm = ref<any>({ question: '', answer: '', category: '' })

const preview = computed(() => renderSafeMarkdown(editing.value?.content ?? ''))

const fail = (fallback: string) => (e: unknown) => { error.value = apiErrorMessage(e, fallback) }

async function loadPosts() {
  posts.value = (await client.get('/admin/content/posts', { params: { search: postSearch.value || undefined } })).data.data
}
async function loadCategories() { categories.value = (await client.get('/admin/content/categories')).data }
async function loadFaqs() { faqs.value = (await client.get('/admin/content/faqs')).data }

function newPost() {
  editing.value = { title: '', slug: '', excerpt: '', content: '', category_id: null, featured_image: '', status: 'draft', published_at: '', seo_title: '', seo_description: '' }
  tagsText.value = ''
}

async function editPost(id: string) {
  try {
    const p = (await client.get(`/admin/content/posts/${id}`)).data
    editing.value = { ...p, published_at: p.published_at ? p.published_at.slice(0, 16) : '' }
    tagsText.value = (p.tags ?? []).join(', ')
  } catch (e) { fail('Could not open the post.')(e) }
}

async function savePost() {
  busy.value = true
  error.value = ''
  const e = editing.value
  const payload = {
    title: e.title, slug: e.slug || null, excerpt: e.excerpt || null, content: e.content,
    category_id: e.category_id || null, featured_image: e.featured_image || null, status: e.status,
    published_at: e.status === 'scheduled' ? e.published_at : null,
    tags: tagsText.value.split(',').map((t) => t.trim()).filter(Boolean),
    seo_title: e.seo_title || null, seo_description: e.seo_description || null,
  }
  try {
    if (e.id) await client.put(`/admin/content/posts/${e.id}`, payload)
    else await client.post('/admin/content/posts', payload)
    editing.value = null
    await loadPosts()
  } catch (err) { fail('Could not save the post.')(err) } finally { busy.value = false }
}

async function deletePost(p: any) {
  if (!window.confirm(`Delete “${p.title}”?`)) return
  await client.delete(`/admin/content/posts/${p.id}`).then(loadPosts).catch(fail('Delete failed.'))
}

async function addCategory() {
  await client.post('/admin/content/categories', { name: newCategory.value })
    .then(() => { newCategory.value = ''; return loadCategories() })
    .catch(fail('Could not add the category.'))
}
async function deleteCategory(c: any) {
  await client.delete(`/admin/content/categories/${c.id}`).then(loadCategories).catch(fail('Delete failed.'))
}

function resetFaq() { faqForm.value = { question: '', answer: '', category: '' } }
async function saveFaq() {
  const f = faqForm.value
  const payload = { question: f.question, answer: f.answer, category: f.category || null }
  const req = f.id ? client.put(`/admin/content/faqs/${f.id}`, payload) : client.post('/admin/content/faqs', { ...payload, is_active: true })
  await req.then(() => { resetFaq(); return loadFaqs() }).catch(fail('Could not save the FAQ.'))
}
async function toggleFaq(f: any) {
  await client.put(`/admin/content/faqs/${f.id}`, { is_active: !f.is_active }).then(loadFaqs).catch(fail('Update failed.'))
}
async function deleteFaq(f: any) {
  if (!window.confirm('Delete this FAQ?')) return
  await client.delete(`/admin/content/faqs/${f.id}`).then(loadFaqs).catch(fail('Delete failed.'))
}

function fmt(d: string) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }

onMounted(() => Promise.all([loadPosts(), loadCategories(), loadFaqs()]).catch(fail('Could not load content.')))
</script>

<style scoped>
.prose-content :deep(h2) { font-size: 1.25rem; font-weight: 800; margin: 1.25rem 0 .5rem; color: var(--text-primary); }
.prose-content :deep(h3) { font-size: 1.05rem; font-weight: 700; margin: 1rem 0 .4rem; color: var(--text-primary); }
.prose-content :deep(p), .prose-content :deep(li) { font-size: .9rem; line-height: 1.7; color: var(--text-secondary); }
.prose-content :deep(p) { margin: .6rem 0; }
.prose-content :deep(ul) { list-style: disc; padding-left: 1.25rem; }
.prose-content :deep(ol) { list-style: decimal; padding-left: 1.25rem; }
.prose-content :deep(a) { color: var(--accent-teal); text-decoration: underline; }
.prose-content :deep(code) { font-size: .8rem; padding: .1rem .3rem; border-radius: 4px; background: var(--surface-overlay); }
.prose-content :deep(blockquote) { border-left: 3px solid var(--accent-teal); padding-left: .8rem; color: var(--text-muted); }
</style>
