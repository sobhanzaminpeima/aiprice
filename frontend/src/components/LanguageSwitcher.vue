<template>
  <label class="lang" :class="`lang--${variant}`">
    <span class="sr-only">{{ $t('Language') }}</span>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
      <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18" />
    </svg>
    <select :value="locale" @change="setLocale(($event.target as HTMLSelectElement).value as Locale)">
      <option v-for="l in LOCALES" :key="l.code" :value="l.code">{{ variant === 'footer' ? l.label : l.short }}</option>
    </select>
  </label>
</template>

<script setup lang="ts">
import { LOCALES, locale, setLocale, type Locale } from '@/i18n'

withDefaults(defineProps<{ variant?: 'nav' | 'footer' }>(), { variant: 'nav' })
</script>

<style scoped>
.lang { position:relative;display:inline-flex;align-items:center;gap:4px;height:36px;padding:0 8px;border-radius:10px;cursor:pointer;
  color:var(--text-secondary);background:var(--surface-overlay);border:1px solid var(--border-subtle) }
.lang select { appearance:none;background:transparent;border:none;color:inherit;font:600 13px/1 inherit;cursor:pointer;padding:0 2px;outline:none }
.lang:focus-within { outline:2px solid var(--focus-ring);outline-offset:2px }
.lang select option { color:#0b1726;background:#fff }
.lang--footer { color:#d7e3ef;background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.16) }
</style>
