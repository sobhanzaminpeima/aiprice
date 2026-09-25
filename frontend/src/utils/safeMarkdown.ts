/**
 * A deliberately small Markdown subset for blog posts: headings (## / ###),
 * paragraphs, bullet and numbered lists, **bold**, *italic*, `code` and
 * [links](https://…). Everything is HTML-escaped first and only the markup
 * produced here is emitted, so post content can never inject script or
 * attributes. Links are restricted to http(s) and open safely.
 */
const escape = (s: string) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')

function inline(text: string): string {
  return escape(text)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>')
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener nofollow ugc">$1</a>')
}

export function renderSafeMarkdown(source: string): string {
  const blocks = (source ?? '').replace(/\r\n/g, '\n').split(/\n{2,}/)

  return blocks
    .map((block) => {
      const lines = block.split('\n').filter((l) => l.trim() !== '')
      if (!lines.length) return ''

      const first = lines[0]!
      if (/^###\s+/.test(first)) return `<h3>${inline(first.replace(/^###\s+/, ''))}</h3>`
      if (/^##\s+/.test(first)) return `<h2>${inline(first.replace(/^##\s+/, ''))}</h2>`
      if (/^#\s+/.test(first)) return `<h2>${inline(first.replace(/^#\s+/, ''))}</h2>`

      if (lines.every((l) => /^\s*[-*]\s+/.test(l))) {
        return `<ul>${lines.map((l) => `<li>${inline(l.replace(/^\s*[-*]\s+/, ''))}</li>`).join('')}</ul>`
      }
      if (lines.every((l) => /^\s*\d+[.)]\s+/.test(l))) {
        return `<ol>${lines.map((l) => `<li>${inline(l.replace(/^\s*\d+[.)]\s+/, ''))}</li>`).join('')}</ol>`
      }
      if (lines.every((l) => /^>\s?/.test(l))) {
        return `<blockquote>${lines.map((l) => inline(l.replace(/^>\s?/, ''))).join('<br>')}</blockquote>`
      }

      return `<p>${lines.map(inline).join('<br>')}</p>`
    })
    .join('\n')
}
