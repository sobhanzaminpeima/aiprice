import { watchEffect, onBeforeUnmount } from 'vue'

export interface PageMeta {
  title?: string
  description?: string
  image?: string | null
  type?: 'website' | 'article' | 'product'
  /** Absolute or root-relative canonical URL; defaults to the current path without query. */
  canonical?: string
  noindex?: boolean
  /** JSON-LD object(s) for rich results. */
  jsonLd?: Record<string, unknown> | Record<string, unknown>[]
}

const SITE = 'AIPrice.app'
const DEFAULT_TITLE = 'AIPrice.app — AI-Powered Marketplace Intelligence'
const DEFAULT_DESCRIPTION =
  'AIPrice.app — Know the value, not just the price. AI price estimates for cars and property in North Cyprus and Turkey.'

function setMeta(attr: 'name' | 'property', key: string, content: string | undefined | null) {
  let el = document.head.querySelector<HTMLMetaElement>(`meta[${attr}="${key}"]`)
  if (!content) {
    el?.remove()
    return
  }
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    el.dataset.managed = 'page-meta'
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function setLink(rel: string, href: string | null) {
  let el = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`)
  if (!href) {
    el?.remove()
    return
  }
  if (!el) {
    el = document.createElement('link')
    el.rel = rel
    document.head.appendChild(el)
  }
  el.href = href
}

function setJsonLd(data: PageMeta['jsonLd']) {
  document.head.querySelectorAll('script[data-page-meta="ld"]').forEach((s) => s.remove())
  if (!data) return
  const s = document.createElement('script')
  s.type = 'application/ld+json'
  s.dataset.pageMeta = 'ld'
  // JSON.stringify output can't break out of the script tag once "<" is escaped.
  s.textContent = JSON.stringify(data).replace(/</g, '\\u003c')
  document.head.appendChild(s)
}

function apply(meta: PageMeta | null | undefined | false) {
  const m = meta || {}
  const title = m.title ? `${m.title} | ${SITE}` : DEFAULT_TITLE
  const description = m.description?.slice(0, 160) || DEFAULT_DESCRIPTION
  const canonical = new URL(m.canonical ?? window.location.pathname, window.location.origin).toString()
  const image = m.image ? new URL(m.image, window.location.origin).toString() : new URL('/icon-512.png', window.location.origin).toString()

  document.title = title
  setMeta('name', 'description', description)
  setMeta('name', 'robots', m.noindex ? 'noindex, nofollow' : 'index, follow')
  setMeta('property', 'og:site_name', SITE)
  setMeta('property', 'og:title', title)
  setMeta('property', 'og:description', description)
  setMeta('property', 'og:type', m.type ?? 'website')
  setMeta('property', 'og:url', canonical)
  setMeta('property', 'og:image', image)
  setMeta('name', 'twitter:card', 'summary_large_image')
  setMeta('name', 'twitter:title', title)
  setMeta('name', 'twitter:description', description)
  setMeta('name', 'twitter:image', image)
  setLink('canonical', canonical)
  setJsonLd(m.jsonLd)
}

/**
 * Keeps <title>, description, Open Graph, canonical and JSON-LD in sync with
 * reactive page data. Pass a getter; it re-applies whenever its inputs change
 * and restores the site defaults when the page unmounts.
 */
export function usePageMeta(getter: () => PageMeta | null | undefined | false) {
  const stop = watchEffect(() => apply(getter()))
  onBeforeUnmount(() => {
    stop()
    apply(null)
  })
}
