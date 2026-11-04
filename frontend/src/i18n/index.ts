/**
 * Lightweight i18n: English source text is the key, so untranslated strings
 * simply fall back to English. Templates use the global $t(); scripts import
 * t(). Persian switches the whole page to right-to-left.
 */
import { ref, type App } from 'vue'
import tr from './tr'
import fa from './fa'
import en from './en'

export type Locale = 'en' | 'tr' | 'fa'
export const LOCALES: { code: Locale; label: string; short: string }[] = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'tr', label: 'Türkçe', short: 'TR' },
  { code: 'fa', label: 'فارسی', short: 'FA' },
]

const dictionaries: Record<Locale, Record<string, string>> = { en, tr, fa }
const STORAGE_KEY = 'aiprice_lang'

function detect(): Locale {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved === 'en' || saved === 'tr' || saved === 'fa') return saved
  } catch { /* storage blocked */ }
  const nav = (typeof navigator !== 'undefined' ? navigator.language : 'en').toLowerCase()
  if (nav.startsWith('tr')) return 'tr'
  if (nav.startsWith('fa')) return 'fa'
  return 'en'
}

export const locale = ref<Locale>(detect())

function apply(l: Locale) {
  const el = document.documentElement
  el.lang = l
  el.dir = l === 'fa' ? 'rtl' : 'ltr'
}

export function setLocale(l: Locale) {
  locale.value = l
  try { localStorage.setItem(STORAGE_KEY, l) } catch { /* ignore */ }
  apply(l)
}

export function t(key: string, params?: Record<string, string | number>): string {
  const k = key.replace(/\s+/g, ' ').trim()
  let s = dictionaries[locale.value][k] ?? en[k] ?? k
  if (params) for (const [p, v] of Object.entries(params)) s = s.replace(new RegExp(`\{${p}\}`, 'g'), String(v))
  return s
}

/** Number in the reader's digits: Persian uses Eastern Arabic numerals. */
export function n(value: number | string | null | undefined): string {
  const num = Number(value ?? 0)
  return num.toLocaleString(locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB')
}

export function useI18n() {
  return { t, n, locale, setLocale }
}

export const i18n = {
  install(app: App) {
    apply(locale.value)
    app.config.globalProperties.$t = t
    app.config.globalProperties.$n = n
  },
}

declare module 'vue' {
  interface ComponentCustomProperties {
    $t: typeof t
    $n: typeof n
  }
}
