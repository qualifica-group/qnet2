import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { AiMarkdown } from '@/features/ai-assistant/components/ai-markdown'
import { parseMarkdown } from '@/features/ai-assistant/markdown-parse'

/** Spec 0347 AC-025: minimal markdown as React elements, never as HTML. */

describe('AiMarkdown', () => {
  it('renders bold, italic, inline code, lists and quotes as elements', () => {
    const { container } = render(
      <AiMarkdown content={'Ciao **grassetto** e *corsivo* con `codice`\n\n- uno\n- due\n\n1. primo\n2. secondo\n\n> citazione'} />,
    )

    expect(screen.getByText('grassetto').tagName).toBe('STRONG')
    expect(screen.getByText('corsivo').tagName).toBe('EM')
    expect(screen.getByText('codice').tagName).toBe('CODE')
    expect(screen.getAllByRole('list')).toHaveLength(2)
    expect(screen.getAllByRole('listitem')).toHaveLength(4)
    expect(container.querySelector('ol')).not.toBeNull()
    expect(container.querySelector('blockquote')?.textContent).toBe('citazione')
  })

  it('keeps a script tag as plain text and never creates the element', () => {
    const { container } = render(<AiMarkdown content={'<script>alert(1)</script> e <img src=x onerror=alert(1)>'} />)

    expect(container.querySelector('script')).toBeNull()
    expect(container.querySelector('img')).toBeNull()
    expect(container.textContent).toContain('<script>alert(1)</script>')
    expect(container.textContent).toContain('<img src=x onerror=alert(1)>')
  })

  it('does not interpret markup inside inline code or fenced blocks', () => {
    const { container } = render(<AiMarkdown content={'`<b>x</b>`\n\n```\n<i>y</i>\n```'} />)

    expect(container.querySelector('b')).toBeNull()
    expect(container.querySelector('i')).toBeNull()
    expect(container.querySelector('pre')?.textContent).toBe('<i>y</i>')
  })

  it('renders a GFM table with header, rows and inline formatting in cells', () => {
    const { container } = render(
      <AiMarkdown content={'Anteprima:\n\n| Campo | Valore |\n|---|:---:|\n| Nome | **Rossi** |\n| Note | `x` |\n\nFine'} />,
    )

    expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Campo', 'Valore'])
    expect(screen.getAllByRole('row')).toHaveLength(3)
    expect(screen.getByText('Rossi').tagName).toBe('STRONG')
    expect(container.querySelector('table')?.parentElement).toHaveClass('overflow-x-auto')
    expect(screen.getByText('Fine').tagName).toBe('P')
  })

  it('pads or trims rows to the header width', () => {
    render(<AiMarkdown content={'| A | B |\n| - | - |\n| 1 |\n| 1 | 2 | 3 |'} />)

    expect(screen.getAllByRole('row').map((row) => row.querySelectorAll('th,td').length)).toEqual([2, 2, 2])
  })

  it('keeps text with pipes that is not a table as text', () => {
    const noSeparator = parseMarkdown('| a | b |\n| c | d |')
    const mismatched = parseMarkdown('| a | b |\n|---|')
    const inProse = parseMarkdown('scegli a | b\n---')

    for (const blocks of [noSeparator, mismatched, inProse]) {
      expect(blocks.every((block) => block.type !== 'table')).toBe(true)
    }
  })

  it('never turns table cells into HTML or links', () => {
    const { container } = render(
      <AiMarkdown content={'| Campo | Valore |\n|---|---|\n| <img src=x onerror=alert(1)> | [x](javascript:alert(1)) |'} />,
    )

    expect(container.querySelector('img')).toBeNull()
    expect(container.querySelector('a')).toBeNull()
    expect(container.querySelector('td')?.textContent).toBe('<img src=x onerror=alert(1)>')
    expect(container.querySelectorAll('td')[1].textContent).toBe('[x](javascript:alert(1))')
  })

  it('leaves unbalanced markers as text', () => {
    const blocks = parseMarkdown('un **grassetto mai chiuso')
    expect(blocks).toEqual([{ type: 'paragraph', children: [{ type: 'text', value: 'un **grassetto mai chiuso' }] }])
  })
})
