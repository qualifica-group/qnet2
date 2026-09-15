/**
 * D-13/AC-025: a rich text field (task/template `description`, note `body`)
 * is logged as sanitized HTML, so the diff row must show its VISIBLE text,
 * never the markup — `renderChangeValue` has no sanitizer of its own and this
 * never touches the DOM (`DOMParser` output is discarded, not mounted).
 */

/** A changed value looks like an HTML fragment when, trimmed, it starts with `<` (D-13). */
export function looksLikeHtmlValue(value: string): boolean {
  return value.trim().startsWith('<')
}

/**
 * Block-level tags of the D-1 allow-list (`p, h2, h3, li, blockquote, pre`):
 * `Node.textContent` concatenates adjacent blocks with no separator at all
 * (`<p>Uno</p><p>Due</p>` -> `"UnoDue"`), so a space is appended after each
 * one walking the tree, then collapsed alongside every other run of whitespace.
 */
const BLOCK_TAG_NAMES = new Set(['P', 'H2', 'H3', 'LI', 'BLOCKQUOTE', 'PRE'])

function appendVisibleText(node: Node, parts: string[]): void {
  if (node.nodeType === Node.TEXT_NODE) {
    parts.push(node.textContent ?? '')
    return
  }
  if (node.nodeName === 'BR') {
    parts.push(' ')
    return
  }
  for (const child of Array.from(node.childNodes)) {
    appendVisibleText(child, parts)
  }
  if (BLOCK_TAG_NAMES.has(node.nodeName)) {
    parts.push(' ')
  }
}

/** Extracts the parsed document's visible text, collapsing runs of whitespace left by block tags. */
export function extractPlainTextFromHtml(html: string): string {
  const parts: string[] = []
  appendVisibleText(new DOMParser().parseFromString(html, 'text/html').body, parts)
  return parts.join('').replace(/\s+/g, ' ').trim()
}
