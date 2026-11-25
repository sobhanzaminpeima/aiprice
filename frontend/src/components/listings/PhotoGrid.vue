<template>
  <div>
    <div class="grid gap-2" :class="images.length > 1 ? 'grid-cols-4 grid-rows-3' : 'grid-cols-1'" :style="images.length > 1 ? 'height:clamp(260px,38vw,460px)' : 'height:clamp(260px,38vw,460px)'">
      <button type="button" class="relative overflow-hidden rounded-2xl" :class="images.length > 1 ? 'col-span-3 row-span-3' : ''" style="background:var(--bg-elevated)" @click="open(0)" :aria-label="$t('Open photo')">
        <img v-if="images[0]" :src="images[0]" :alt="title" class="w-full h-full object-cover" fetchpriority="high" />
        <span v-else class="w-full h-full flex items-center justify-center opacity-30"><slot name="empty" /></span>
        <span class="absolute bottom-3 right-3 px-2.5 py-1 rounded-lg text-xs font-semibold" style="background:rgba(8,24,39,.7);color:#fff;backdrop-filter:blur(6px)" v-if="images.length">⤢ {{ images.length }} {{ $t(images.length === 1 ? 'photo' : 'photos') }}</span>
        <slot name="overlay" />
      </button>
      <button v-for="(img, i) in side" :key="img + i" type="button" class="relative overflow-hidden rounded-xl" style="background:var(--bg-elevated)" @click="open(i + 1)" :aria-label="`${$t('Open photo')} ${i + 2}`">
        <img :src="img" :alt="`${title} — ${i + 2}`" loading="lazy" class="w-full h-full object-cover" />
        <span v-if="i === side.length - 1 && more > 0" class="absolute inset-0 flex items-center justify-center font-bold text-sm" style="background:rgba(8,24,39,.62);color:#fff">+{{ more }} {{ $t('more') }}</span>
      </button>
    </div>

    <div v-if="lightbox !== null" class="fixed inset-0 z-[70] flex items-center justify-center p-4" style="background:rgba(4,10,20,.92)" role="dialog" aria-modal="true" @click.self="lightbox = null" @keydown.esc="lightbox = null" @keydown.left="step(-1)" @keydown.right="step(1)" tabindex="0" ref="box">
      <button class="absolute top-4 right-4 w-10 h-10 rounded-full text-xl" style="background:rgba(255,255,255,.12);color:#fff" @click="lightbox = null" :aria-label="$t('Close')">×</button>
      <button v-if="images.length > 1" class="absolute left-3 md:left-8 w-11 h-11 rounded-full text-2xl" style="background:rgba(255,255,255,.12);color:#fff" @click="step(-1)" :aria-label="$t('Previous photo')">‹</button>
      <img :src="images[lightbox]" :alt="title" class="max-h-[88vh] max-w-[92vw] rounded-xl object-contain" />
      <button v-if="images.length > 1" class="absolute right-3 md:right-8 w-11 h-11 rounded-full text-2xl" style="background:rgba(255,255,255,.12);color:#fff" @click="step(1)" :aria-label="$t('Next photo')">›</button>
      <span class="absolute bottom-4 text-xs" style="color:#cfe0f3">{{ lightbox + 1 }} / {{ images.length }}</span>
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'

const props = defineProps<{ images: string[]; title: string }>()
const lightbox = ref<number | null>(null)
const box = ref<HTMLElement | null>(null)
const side = computed(() => props.images.slice(1, 4))
const more = computed(() => Math.max(0, props.images.length - 4))
async function open(i: number) { if (!props.images.length) return; lightbox.value = i; await nextTick(); box.value?.focus() }
function step(d: number) { if (lightbox.value === null) return; lightbox.value = (lightbox.value + d + props.images.length) % props.images.length }
</script>
