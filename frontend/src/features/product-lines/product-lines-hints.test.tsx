import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductLinesHints } from '@/features/product-lines/product-lines-hints'

/** User request 2026-11-18: the product-line guidance is one bulleted box, not loose paragraphs. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductLinesHints', () => {
  it('lists the field hint and the form lines as items of one list', () => {
    render(<ProductLinesHints singleRootsBlocked={false} extraHints={['Request line']} />)

    const items = screen.getAllByRole('listitem').map((item) => item.textContent)
    expect(items).toHaveLength(2)
    expect(items[0]).toContain('parent category')
    expect(items[1]).toBe('Request line')
  })

  it('adds the single-row reason only when single roots are blocked', () => {
    const { rerender } = render(<ProductLinesHints singleRootsBlocked={false} extraHints={[]} />)
    expect(screen.queryByText('Parent categories managed as a single row cannot be added beside other rows.')).toBeNull()

    rerender(<ProductLinesHints singleRootsBlocked extraHints={[]} />)
    expect(screen.getByText('Parent categories managed as a single row cannot be added beside other rows.')).toBeInTheDocument()
  })
})
