import type { HelpBlock, HelpGuide } from '@/features/help/types'

function serializeBlock(block: HelpBlock): string {
  switch (block.type) {
    case 'paragraph':
      return block.text
    case 'steps':
      return block.items.map((item, index) => `${index + 1}. ${item}`).join('\n')
    case 'list':
      return block.items.map((item) => `- ${item}`).join('\n')
    case 'table':
      return [block.headers, ...block.rows].map((row) => row.join(' | ')).join('\n')
    case 'tip':
      return `Tip: ${block.text}`
    case 'warning':
      return `Warning: ${block.text}`
    case 'note':
      return `Note: ${block.text}`
  }
}

/**
 * Serializes a help guide to plain text for the assistant's system prompt
 * (AC-024): title, summary, then each section with its blocks. Truncated to
 * `maxChars` so the server never answers 422 on the guide length.
 */
export function serializeHelpGuide(guide: HelpGuide, maxChars: number): string {
  const parts = [guide.title, guide.summary]
  for (const section of guide.sections) {
    parts.push(`## ${section.title}`, ...section.blocks.map(serializeBlock))
  }
  const text = parts.filter((part) => part.length > 0).join('\n\n')
  return text.length > maxChars ? text.slice(0, maxChars) : text
}
