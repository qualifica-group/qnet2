import { RICH_TEXT_MENTION_NODE_NAME } from '@/components/rich-text/rich-text-extensions'

/**
 * HTML-derived helpers for the note composer (spec 0128 D-2/D-5/D-7): both
 * read straight off the sanitized fragment the editor emits, never off a
 * parallel piece of state that could drift from what the doc actually holds.
 * `DOMParser` output here is never attached to the live document (unlike
 * `dangerouslySetInnerHTML`), so parsing untrusted HTML this way is inert.
 */

/** Visible text length of a rich text field (D-5 `note_text_max`), tags stripped. */
export function getVisibleTextLength(html: string | null): number {
  if (!html) {
    return 0
  }
  return new DOMParser().parseFromString(html, 'text/html').body.textContent?.length ?? 0
}

/** Deduplicated mention ids, in order of first appearance (D-7: read from the DOM nodes, not a regex). */
export function extractMentionIds(html: string | null): number[] {
  if (!html) {
    return []
  }
  const doc = new DOMParser().parseFromString(html, 'text/html')
  const ids: number[] = []
  const seen = new Set<number>()
  doc.querySelectorAll(`span[data-type="${RICH_TEXT_MENTION_NODE_NAME}"][data-id]`).forEach((node) => {
    const id = Number(node.getAttribute('data-id'))
    if (!Number.isNaN(id) && !seen.has(id)) {
      seen.add(id)
      ids.push(id)
    }
  })
  return ids
}
