import type { ReactNode } from 'react'

const BOLD_PATTERN = /\*\*(.+?)\*\*/g

/**
 * The only inline markup the content schema allows (`types.ts`): `**bold**`
 * becomes `<strong>`. Output is plain React nodes (strings and `<strong>`
 * elements) — never `dangerouslySetInnerHTML` — so a literal `<script>` in
 * source text renders as text, not as an element (AC-008).
 */
export function renderHelpInlineText(text: string): ReactNode {
  const nodes: ReactNode[] = []
  let lastIndex = 0
  let keyIndex = 0
  BOLD_PATTERN.lastIndex = 0

  let match = BOLD_PATTERN.exec(text)
  while (match !== null) {
    if (match.index > lastIndex) {
      nodes.push(text.slice(lastIndex, match.index))
    }
    nodes.push(<strong key={`bold-${keyIndex}`}>{match[1]}</strong>)
    keyIndex += 1
    lastIndex = match.index + match[0].length
    match = BOLD_PATTERN.exec(text)
  }

  if (lastIndex < text.length) {
    nodes.push(text.slice(lastIndex))
  }

  return nodes
}
