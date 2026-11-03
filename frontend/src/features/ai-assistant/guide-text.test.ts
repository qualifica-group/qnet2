import { describe, expect, it } from 'vitest'
import { serializeHelpGuide } from '@/features/ai-assistant/guide-text'
import type { HelpGuide } from '@/features/help/types'

const guide: HelpGuide = {
  key: 'x',
  title: 'Title',
  summary: 'Summary',
  sections: [
    {
      id: 's1',
      title: 'Section',
      blocks: [
        { type: 'paragraph', text: 'Para' },
        { type: 'steps', items: ['one', 'two'] },
        { type: 'list', items: ['a'] },
        { type: 'table', headers: ['H1', 'H2'], rows: [['a', 'b']] },
        { type: 'tip', text: 't' },
        { type: 'warning', text: 'w' },
        { type: 'note', text: 'n' },
      ],
    },
  ],
}

describe('serializeHelpGuide', () => {
  it('serializes every block type to plain text', () => {
    const text = serializeHelpGuide(guide, 10_000)
    expect(text).toContain('Title\n\nSummary\n\n## Section\n\nPara')
    expect(text).toContain('1. one\n2. two')
    expect(text).toContain('- a')
    expect(text).toContain('H1 | H2\na | b')
    expect(text).toContain('Tip: t')
    expect(text).toContain('Warning: w')
    expect(text).toContain('Note: n')
  })

  it('truncates to the given maximum (AC-024)', () => {
    expect(serializeHelpGuide(guide, 12)).toHaveLength(12)
  })
})
