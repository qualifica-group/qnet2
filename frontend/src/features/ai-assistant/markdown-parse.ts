/**
 * Minimal markdown model for assistant messages (spec 0347, AC-025): paragraphs,
 * headings (rendered as emphasis), bullet/numbered lists, quotes, fenced code
 * and inline bold / italic / code. Pure functions: the output is rendered as
 * React elements, never as HTML.
 */
export type InlineNode =
  | { type: 'text'; value: string }
  | { type: 'bold'; children: InlineNode[] }
  | { type: 'italic'; children: InlineNode[] }
  | { type: 'code'; value: string }
  | { type: 'break' }

export type BlockNode =
  | { type: 'paragraph'; children: InlineNode[] }
  | { type: 'heading'; children: InlineNode[] }
  | { type: 'list'; ordered: boolean; items: InlineNode[][] }
  | { type: 'quote'; children: InlineNode[] }
  | { type: 'code'; value: string }

const INLINE_PATTERN = /`([^`\n]+)`|\*\*([^*\n]+?)\*\*|\*([^*\n]+?)\*/
const FENCE = /^```/
const HEADING = /^#{1,6}\s+(.*)$/
const BULLET = /^\s*[-*+]\s+(.*)$/
const NUMBERED = /^\s*\d+[.)]\s+(.*)$/
const QUOTE = /^>\s?(.*)$/

export function parseInline(text: string): InlineNode[] {
  const nodes: InlineNode[] = []
  let rest = text
  for (;;) {
    const match = INLINE_PATTERN.exec(rest)
    if (!match) {
      break
    }
    if (match.index > 0) {
      nodes.push({ type: 'text', value: rest.slice(0, match.index) })
    }
    if (match[1] !== undefined) {
      nodes.push({ type: 'code', value: match[1] })
    } else if (match[2] !== undefined) {
      nodes.push({ type: 'bold', children: parseInline(match[2]) })
    } else {
      nodes.push({ type: 'italic', children: parseInline(match[3]) })
    }
    rest = rest.slice(match.index + match[0].length)
  }
  if (rest.length > 0) {
    nodes.push({ type: 'text', value: rest })
  }
  return nodes
}

/** Inline content of consecutive lines, joined by explicit line breaks. */
function parseLines(lines: string[]): InlineNode[] {
  return lines.flatMap((line, index) => [
    ...(index > 0 ? [{ type: 'break' } as const] : []),
    ...parseInline(line),
  ])
}

export function parseMarkdown(source: string): BlockNode[] {
  const lines = source.replace(/\r\n?/g, '\n').split('\n')
  const blocks: BlockNode[] = []
  let index = 0

  while (index < lines.length) {
    const line = lines[index]

    if (line.trim() === '') {
      index += 1
    } else if (FENCE.test(line.trim())) {
      const code: string[] = []
      index += 1
      while (index < lines.length && !FENCE.test(lines[index].trim())) {
        code.push(lines[index])
        index += 1
      }
      index += 1
      blocks.push({ type: 'code', value: code.join('\n') })
    } else if (HEADING.test(line)) {
      blocks.push({ type: 'heading', children: parseInline(HEADING.exec(line)![1]) })
      index += 1
    } else if (BULLET.test(line) || NUMBERED.test(line)) {
      const ordered = NUMBERED.test(line) && !BULLET.test(line)
      const pattern = ordered ? NUMBERED : BULLET
      const items: InlineNode[][] = []
      while (index < lines.length && pattern.test(lines[index])) {
        items.push(parseInline(pattern.exec(lines[index])![1]))
        index += 1
      }
      blocks.push({ type: 'list', ordered, items })
    } else if (QUOTE.test(line)) {
      const quoted: string[] = []
      while (index < lines.length && QUOTE.test(lines[index])) {
        quoted.push(QUOTE.exec(lines[index])![1])
        index += 1
      }
      blocks.push({ type: 'quote', children: parseLines(quoted) })
    } else {
      const paragraph: string[] = []
      while (index < lines.length && lines[index].trim() !== '' && !isBlockStart(lines[index])) {
        paragraph.push(lines[index])
        index += 1
      }
      blocks.push({ type: 'paragraph', children: parseLines(paragraph) })
    }
  }

  return blocks
}

function isBlockStart(line: string): boolean {
  return (
    FENCE.test(line.trim()) ||
    HEADING.test(line) ||
    BULLET.test(line) ||
    NUMBERED.test(line) ||
    QUOTE.test(line)
  )
}
