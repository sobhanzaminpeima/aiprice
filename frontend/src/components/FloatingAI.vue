<template>
  <!-- Floating AI Button -->
  <div class="floating-ai-wrap" v-if="!hidden">
    <!-- Chat panel -->
    <Transition name="chat-slide">
      <div v-if="chatOpen" class="chat-panel">
        <div class="chat-header">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm" style="background:linear-gradient(135deg,var(--accent-teal),var(--accent-cyan));color:var(--on-accent);font-weight:900">AI</div>
            <div>
              <p class="text-xs font-bold" style="color:var(--text-primary)">AIPrice.app</p>
              <p class="text-xs" style="color:var(--accent-teal)">● Online</p>
            </div>
          </div>
          <button @click="chatOpen = false" style="color:var(--text-muted);font-size:20px;line-height:1">×</button>
        </div>
        <div class="chat-messages" ref="messagesEl">
          <div v-for="msg in messages" :key="msg.id" :class="['chat-msg', msg.role === 'user' ? 'chat-msg--user' : 'chat-msg--ai']">
            <div class="chat-bubble" :class="msg.role === 'user' ? 'bubble-user' : 'bubble-ai'">
              {{ msg.text }}
              <div v-if="msg.results?.length" class="chat-results">
                <RouterLink v-for="r in msg.results" :key="r.id" :to="`/listings/${r.slug}`" class="chat-result-card" @click="chatOpen = false">
                  <div class="flex items-center justify-between gap-2">
                    <span class="chat-result-title">{{ r.title }}</span>
                    <span v-if="r.match_score" class="chat-result-match">{{ r.match_score }}% match</span>
                  </div>
                  <div class="chat-result-price">{{ formatPrice(r.price, r.currency) }} · {{ r.city }}</div>
                </RouterLink>
              </div>
              <div v-if="msg.suggestions?.length" class="chat-suggestions">
                <button v-for="s in msg.suggestions" :key="s" class="chat-suggestion-chip" @click="sendMessage(s)">{{ s }}</button>
              </div>
            </div>
          </div>
          <div v-if="thinking" class="chat-msg chat-msg--ai">
            <div class="chat-bubble bubble-ai" style="display:flex;gap:4px;align-items:center">
              <span class="dot-pulse"></span><span class="dot-pulse" style="animation-delay:.2s"></span><span class="dot-pulse" style="animation-delay:.4s"></span>
            </div>
          </div>
        </div>
        <div class="chat-input-row">
          <input v-model="inputText" @keydown.enter="sendMessage()" type="text" placeholder="Ask about prices, market trends..." class="chat-input" />
          <button @click="sendMessage()" class="chat-send" :disabled="!inputText.trim() || thinking">→</button>
        </div>
      </div>
    </Transition>

    <!-- Trigger button -->
    <button @click="chatOpen = !chatOpen" class="ai-fab" :class="{ 'ai-fab--active': chatOpen }" aria-label="AI Assistant">
      <span v-if="!chatOpen" class="fab-icon">✦</span>
      <span v-else class="fab-icon" style="font-size:18px">×</span>
      <span v-if="!chatOpen" class="fab-label">AI</span>
    </button>
  </div>
</template>
<script setup lang="ts">
import { ref, nextTick } from "vue"
import { RouterLink } from "vue-router"
import client from "@/api/client"

interface ChatResult { id: string; slug: string; title: string; price: number | string; currency: string; city: string; match_score: number }
interface ChatMsg { id: number; role: "user" | "ai"; text: string; results?: ChatResult[]; suggestions?: string[] }

const chatOpen = ref(false); const hidden = ref(false); const thinking = ref(false); const inputText = ref(""); const messagesEl = ref<HTMLElement>()
const messages = ref<ChatMsg[]>([
  {
    id: 1, role: "ai",
    text: "Hi! Tell me what you're looking for — e.g. \"family SUV under £20,000\" — and I'll search real listings for you.",
    suggestions: ["Car", "Property", "Rental"],
  },
])
let nextId = 2

function formatPrice(price: number | string, currency: string) {
  return Number(price).toLocaleString() + " " + currency
}

async function sendMessage(preset?: string) {
  const text = (preset ?? inputText.value).trim()
  if (!text) return
  messages.value.push({ id: nextId++, role: "user", text })
  inputText.value = ""; thinking.value = true
  await scrollBottom()

  try {
    const { data } = await client.post("/chat", { message: text })
    messages.value.push({
      id: nextId++, role: "ai",
      text: data.message,
      results: data.results,
      suggestions: data.suggestions,
    })
  } catch {
    messages.value.push({
      id: nextId++, role: "ai",
      text: "Sorry, I couldn't reach the search service just now. Please try again in a moment.",
    })
  } finally {
    thinking.value = false
    await scrollBottom()
  }
}
async function scrollBottom() {
  await nextTick()
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
}
</script>
<style scoped>
.floating-ai-wrap { position: fixed; bottom: 88px; right: 16px; z-index: 100; display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }
@media (min-width: 768px) {
  .floating-ai-wrap { bottom: 24px; right: 24px; }
}
.ai-fab { width: 56px; height: 56px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; background: linear-gradient(135deg,var(--accent-teal),var(--accent-cyan)); border: none; cursor: pointer; box-shadow: 0 4px 24px rgba(var(--accent-teal-rgb),0.35); transition: transform 0.2s, box-shadow 0.2s; }
.ai-fab:hover { transform: scale(1.08); box-shadow: 0 6px 32px rgba(var(--accent-teal-rgb),0.5); }
.ai-fab--active { background: linear-gradient(135deg,var(--bg-surface),var(--bg-elevated)); border: 1px solid rgba(var(--accent-teal-rgb),0.3); box-shadow: 0 4px 24px rgba(var(--accent-teal-rgb),0.15); }
.fab-icon { font-size: 20px; color: var(--bg-base); font-weight: 900; line-height: 1; }
.ai-fab--active .fab-icon { color: var(--accent-teal); }
.fab-label { font-size: 9px; font-weight: 900; color: var(--bg-base); letter-spacing: 0.1em; }
.chat-panel { width: 320px; border-radius: 20px; overflow: hidden; background: var(--bg-surface); border: 1px solid rgba(var(--accent-teal-rgb),0.2); box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(var(--accent-teal-rgb),0.06); display: flex; flex-direction: column; max-height: 460px; }
.chat-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 16px 12px; background: rgba(var(--accent-teal-rgb),0.04); border-bottom: 1px solid rgba(var(--accent-teal-rgb),0.12); }
.chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; min-height: 200px; scrollbar-width: thin; scrollbar-color: var(--scroll-thumb) transparent; }
.chat-msg { display: flex; }
.chat-msg--user { justify-content: flex-end; }
.chat-bubble { max-width: 84%; padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.5; }
.bubble-ai { background: rgba(var(--accent-teal-rgb),0.07); color: var(--text-secondary); border: 1px solid rgba(var(--accent-teal-rgb),0.1); border-radius: 14px 14px 14px 4px; }
.bubble-user { background: linear-gradient(135deg,var(--accent-teal),var(--accent-cyan)); color: var(--bg-base); font-weight: 600; border-radius: 14px 14px 4px 14px; }
.chat-input-row { display: flex; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border-subtle); }
.chat-input { flex: 1; background: var(--surface-overlay); border: 1px solid var(--border-strong); border-radius: 10px; color: var(--text-primary); padding: 8px 12px; font-size: 13px; outline: none; }
.chat-input:focus { border-color: rgba(var(--accent-teal-rgb),0.4); }
.chat-send { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg,var(--accent-teal),var(--accent-cyan)); color: var(--bg-base); font-size: 16px; font-weight: 900; border: none; cursor: pointer; flex-shrink: 0; }
.chat-send:disabled { opacity: 0.4; cursor: not-allowed; }
.chat-results { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
.chat-result-card { display: block; background: rgba(0,0,0,0.18); border: 1px solid rgba(var(--accent-teal-rgb),0.15); border-radius: 10px; padding: 8px 10px; text-decoration: none; transition: border-color 0.15s; }
.chat-result-card:hover { border-color: rgba(var(--accent-teal-rgb),0.4); }
.chat-result-title { font-size: 12px; font-weight: 700; color: var(--text-primary); }
.chat-result-match { font-size: 10px; font-weight: 800; color: var(--accent-teal); white-space: nowrap; }
.chat-result-price { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.chat-suggestions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.chat-suggestion-chip { font-size: 11px; font-weight: 600; padding: 5px 10px; border-radius: 999px; background: rgba(var(--accent-teal-rgb),0.08); border: 1px solid rgba(var(--accent-teal-rgb),0.25); color: var(--accent-teal); cursor: pointer; transition: background 0.15s; }
.chat-suggestion-chip:hover { background: rgba(var(--accent-teal-rgb),0.16); }
.dot-pulse { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--accent-teal); animation: dot-blink 1.2s ease-in-out infinite; }
@keyframes dot-blink { 0%,80%,100% { transform: scale(0.6); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }
.chat-slide-enter-active, .chat-slide-leave-active { transition: opacity 0.25s, transform 0.3s cubic-bezier(.4,0,.2,1); }
.chat-slide-enter-from, .chat-slide-leave-to { opacity: 0; transform: translateY(16px) scale(0.95); }
</style>