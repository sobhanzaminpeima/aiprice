<template>
  <div>
    <div v-if="langs.length > 1" class="flex gap-2 mb-3" role="tablist">
      <button v-for="l in langs" :key="l" role="tab" :aria-selected="active === l" @click="active = l" class="px-3 py-1.5 rounded-lg text-xs font-bold transition"
        :style="active === l ? 'background:rgba(var(--accent-cyan-rgb),0.12);color:var(--accent-cyan);border:1px solid rgba(var(--accent-cyan-rgb),0.3)' : 'background:var(--surface-overlay);color:var(--text-muted);border:1px solid var(--border-subtle)'">{{ NAMES[l] }}</button>
    </div>
    <p class="text-sm leading-relaxed whitespace-pre-line" style="color:var(--text-secondary)" :lang="active === 'original' ? undefined : active" :dir="active === 'fa' ? 'rtl' : 'ltr'">{{ text }}</p>
    <p v-if="langs.length > 1 && active !== 'original'" class="text-[11px] mt-3" style="color:var(--text-muted)">{{ $t('Translated automatically from the seller\'s text.') }}</p>
  </div>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { locale } from '@/i18n'

const props = defineProps<{ description: string | null; translations?: Record<string, string> | null }>()
const NAMES: Record<string, string> = { original: 'Original', en: 'English', tr: 'Türkçe', fa: 'فارسی' }

const tr = computed(() => props.translations ?? {})
const langs = computed(() => (Object.keys(tr.value).length >= 2 ? ['original', ...['en', 'tr', 'fa'].filter((l) => tr.value[l])] : ['original']))
// Start in the visitor's language when a translation exists, otherwise the seller's own text.
const active = ref<string>('original')
watch([locale, tr], () => { active.value = tr.value[locale.value] ? locale.value : 'original' }, { immediate: true })
const text = computed(() => (active.value === 'original' ? props.description ?? '' : tr.value[active.value] ?? ''))
</script>
