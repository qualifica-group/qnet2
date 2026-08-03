import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'

/**
 * Spec 0075: the "Categoria prodotto" cell reads the row's own {funzione
 * aziendale, categoria} pairs — the very collection its inline editor commits
 * — and renders the CATEGORY names out of them, with the full pairs as the
 * native tooltip.
 */

function renderProductCategories(value: unknown) {
  const renderer = requestManagementColumnRenderers.product_categories

  return render(<>{renderer({ value } as ICellRendererParams)}</>)
}

const PAIRS = [
  {
    business_function_id: 3,
    business_function_name: 'Energia',
    product_category_id: 7,
    product_category_name: 'Luce',
  },
  {
    business_function_id: 3,
    business_function_name: 'Energia',
    product_category_id: 9,
    product_category_name: 'Gas',
  },
]

describe('request-management product_categories cell (spec 0075)', () => {
  it('joins the category names and keeps the pairs in the tooltip', () => {
    renderProductCategories(PAIRS)

    const cell = screen.getByText('Luce, Gas')

    expect(cell).toHaveAttribute('title', 'Energia › Luce\nEnergia › Gas')
  })

  it('falls back to the shared empty cell with no product line', () => {
    const { container } = renderProductCategories([])

    expect(container.textContent).not.toContain('Luce')
    expect(container.textContent?.trim()).not.toBe('')
  })
})
