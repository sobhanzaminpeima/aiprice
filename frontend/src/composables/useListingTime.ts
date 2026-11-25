import { ref } from 'vue'
import { locale } from '@/i18n'

/** Shown to everyone in Cyprus time, so a listing reads the same wherever it is opened from. */
export const SITE_TIME_ZONE = 'Europe/Nicosia'

// One shared clock so every "x minutes ago" on the page refreshes together.
const now = ref(Date.now())
if (typeof window !== 'undefined') window.setInterval(() => (now.value = Date.now()), 60_000)

const intlLocale = () => (locale.value === 'fa' ? 'fa-IR' : locale.value === 'tr' ? 'tr-TR' : 'en-GB')

/** The moment a listing went up: when it went live, or when it was uploaded if it is still in review. */
export function postedAt(l: { published_at?: string | null; created_at?: string | null }): string | null {
  return l.published_at || l.created_at || null
}

/** "just now", "5 minutes ago", "3 hours ago", "yesterday", "2 weeks ago", "3 months ago". */
export function timeAgo(iso?: string | null): string {
  if (!iso) return ''
  const diff = Math.max(0, now.value - new Date(iso).getTime())
  const rtf = new Intl.RelativeTimeFormat(intlLocale(), { numeric: 'auto' })
  const min = Math.floor(diff / 60_000)
  if (min < 1) return rtf.format(0, 'second') // "now"
  if (min < 60) return rtf.format(-min, 'minute')
  const hours = Math.floor(min / 60)
  if (hours < 24) return rtf.format(-hours, 'hour')
  const days = Math.floor(hours / 24)
  if (days < 14) return rtf.format(-days, 'day')
  if (days < 60) return rtf.format(-Math.floor(days / 7), 'week')
  if (days < 365) return rtf.format(-Math.floor(days / 30), 'month')
  return rtf.format(-Math.floor(days / 365), 'year')
}

/** "25 Nov 2026, 14:35" in Cyprus time. */
export function exactTime(iso?: string | null): string {
  if (!iso) return ''
  return new Intl.DateTimeFormat(intlLocale(), { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: SITE_TIME_ZONE })
    .format(new Date(iso))
}
