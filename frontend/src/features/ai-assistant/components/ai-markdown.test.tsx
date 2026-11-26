import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { AiMarkdown } from '@/features/ai-assistant/components/ai-markdown'

/**
 * Spec 0347 AC-025: assistant Markdown as React elements, never as HTML.
 * Requirement changed (26/11/2026, client): the hand-made parser is replaced by react-markdown + remark-gfm;
 * the safety contract (no raw HTML, no links, no images) is unchanged and tested below.
 */

const CONNECTOR_TABLE = '| Campo | Valore |\n|---|---|\n| Stato | Aperta |\n| Importo | 1.250,50 € |'

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
    expect(container.querySelector('blockquote')?.textContent?.trim()).toBe('citazione')
  })

  it('renders headings as short emphasised paragraphs, not as page headings', () => {
    const { container } = render(<AiMarkdown content={'# Titolo\n\ntesto'} />)

    expect(container.querySelector('h1')).toBeNull()
    expect(screen.getByText('Titolo').tagName).toBe('P')
    expect(screen.getByText('Titolo')).toHaveClass('font-semibold')
  })

  it('keeps single line breaks inside a paragraph', () => {
    render(<AiMarkdown content={'riga uno\nriga due'} />)

    expect(screen.getByText(/riga uno/)).toHaveClass('whitespace-pre-line')
  })

  it('does not create elements from raw HTML and keeps it as plain text', () => {
    const { container } = render(<AiMarkdown content={'<script>alert(1)</script> e <img src=x onerror=alert(1)> <b>x</b>'} />)

    for (const tag of ['script', 'img', 'b']) expect(container.querySelector(tag)).toBeNull()
    expect(container.querySelector('[onerror]')).toBeNull()
    expect(container.textContent).toContain('<script>alert(1)</script>')
  })

  it('does not interpret markup inside inline code or fenced blocks', () => {
    const { container } = render(<AiMarkdown content={'`<b>x</b>`\n\n```\n<i>y</i>\n```'} />)

    expect(container.querySelector('b')).toBeNull()
    expect(container.querySelector('i')).toBeNull()
    expect(container.querySelector('pre')?.textContent?.trim()).toBe('<i>y</i>')
    expect(container.querySelector('pre')).toHaveClass('overflow-x-auto')
  })

  it('shows a link as plain text, with no anchor and no javascript: target', () => {
    const { container } = render(<AiMarkdown content={'[clicca](javascript:alert(1)) e [sito](https://example.com) e https://example.org'} />)

    expect(container.querySelector('a')).toBeNull()
    expect(container.querySelector('[href]')).toBeNull()
    expect(container.innerHTML).not.toContain('javascript')
    expect(container.textContent).toContain('clicca')
    expect(container.textContent).toContain('sito')
  })

  it('never renders an image, whether Markdown or HTML, and never requests its source', () => {
    const { container } = render(<AiMarkdown content={'![logo](https://example.com/x.png) <img src="https://example.com/y.png">'} />)

    expect(container.querySelector('img')).toBeNull()
    expect(container.querySelector('[src]')).toBeNull()
  })

  it('renders a GFM table with header, rows, inline formatting and horizontal scroll inside the bubble', () => {
    const { container } = render(
      <AiMarkdown content={'Anteprima:\n\n| Campo | Valore |\n|---|:---:|\n| Nome | **Rossi** |\n| Note | `x` |\n\nFine'} />,
    )

    expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Campo', 'Valore'])
    expect(screen.getAllByRole('row')).toHaveLength(3)
    expect(screen.getByText('Rossi').tagName).toBe('STRONG')
    expect(container.querySelector('table')?.parentElement).toHaveClass('overflow-x-auto', 'max-w-full')
    expect(screen.getByText('Fine').tagName).toBe('P')
  })

  it('renders the connector preview table with distinct header and body rows', () => {
    const { container } = render(<AiMarkdown content={CONNECTOR_TABLE} />)

    expect(container.querySelector('thead')).not.toBeNull()
    expect(container.querySelectorAll('tbody tr')).toHaveLength(2)
    expect(container.textContent).not.toContain('---')
  })

  it('aligns to the right the columns marked by GFM and the columns that are all numbers', () => {
    render(<AiMarkdown content={'| Voce | Qtà | Importo | Nota |\n|---|---|---:|---|\n| A | 3 | 1.250,50 € | 12 anni |\n| B | -4,5 | 10 | ok |'} />)

    const [voce, qta, importo, nota] = screen.getAllByRole('columnheader')
    expect(voce).not.toHaveClass('text-right')
    expect(qta).toHaveClass('text-right')
    expect(importo).toHaveClass('text-right')
    expect(nota).not.toHaveClass('text-right')
    expect(screen.getByText('1.250,50 €')).toHaveClass('text-right')
    expect(screen.getByText('12 anni')).not.toHaveClass('text-right')
  })

  it('keeps a table that is still arriving as plain text until the separator row is complete', () => {
    for (const partial of ['| Campo | Valore |', '| Campo | Valore |\n|---', '| Campo | Valore |\n|---|', '| Campo | Valore |\n|---|---|\n| Sta']) {
      const { container, unmount } = render(<AiMarkdown content={partial} />)

      expect(container.querySelector('table') !== null).toBe(partial.includes('|---|---|'))
      expect(container.textContent).toContain('Campo')
      unmount()
    }
  })

  it('keeps text with pipes that is not a table as text', () => {
    const { container } = render(<AiMarkdown content={'| a | b |\n| c | d |\n\nscegli a | b'} />)

    expect(container.querySelector('table')).toBeNull()
  })

  it('never turns table cells into HTML, links or images', () => {
    const { container } = render(
      <AiMarkdown content={'| Campo | Valore |\n|---|---|\n| <img src=x onerror=alert(1)> | [x](javascript:alert(1)) |\n| <b>b</b> | ![i](https://example.com/i.png) |'} />,
    )

    for (const tag of ['img', 'a', 'b']) expect(container.querySelector(tag)).toBeNull()
    for (const attribute of ['[onerror]', '[href]', '[src]']) expect(container.querySelector(attribute)).toBeNull()
    expect(container.innerHTML).not.toContain('javascript')
  })

  it('leaves unbalanced markers as text', () => {
    render(<AiMarkdown content={'un **grassetto mai chiuso'} />)

    expect(screen.getByText('un **grassetto mai chiuso').tagName).toBe('P')
  })
})
