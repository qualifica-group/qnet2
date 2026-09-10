import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { productCategoryColumnRenderers } from '@/features/product-categories/column-renderers'

/** Task #18: attributes_count/products_count reveal their names in a hover/focus tooltip. */

function renderCell(columnId: string, value: unknown, data: Record<string, unknown>) {
  const renderer = productCategoryColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value, data } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('productCategoryColumnRenderers.parent', () => {
  it('renders the parent name', () => {
    renderCell('parent', { id: 1, name: 'Electronics' }, {})
    expect(screen.getByText('Electronics')).toBeInTheDocument()
  })

  it('renders an em dash for a root category', () => {
    renderCell('parent', null, {})
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('productCategoryColumnRenderers boolean columns', () => {
  // Spec 0091: the two root-owned booleans join the pair — `generates_contract`
  // is a visible column, `single_quote_per_opportunity` had a renderer gap.
  // Spec 0114 adds a fifth: `simplified_offer_line`.
  it.each([
    'requires_quote',
    'is_selectable',
    'single_quote_per_opportunity',
    'generates_contract',
    'simplified_offer_line',
  ])('renders %s as a yes/no badge', (columnId) => {
    const { unmount } = renderCell(columnId, true, {})
    expect(screen.getByText('Yes')).toBeInTheDocument()
    unmount()

    renderCell(columnId, false, {})
    expect(screen.getByText('No')).toBeInTheDocument()
  })

  it('renders an em dash when the flag is missing', () => {
    renderCell('is_selectable', null, {})
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('productCategoryColumnRenderers.attributes_count', () => {
  it('renders an em dash and no count badge when the count is zero', () => {
    renderCell('attributes_count', 0, { attributes: [] })
    expect(screen.getByText('—')).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
  })

  it('lists every attribute name in the tooltip', async () => {
    renderCell('attributes_count', 2, {
      attributes: [
        { id: 1, name: 'Color' },
        { id: 2, name: 'RAM' },
      ],
    })

    expect(screen.getByText('2')).toBeInTheDocument()
    fireEvent.pointerMove(screen.getByLabelText('Color, RAM'))
    await waitFor(() => {
      const tooltip = screen.getByRole('tooltip')
      expect(tooltip).toHaveTextContent('Color')
      expect(tooltip).toHaveTextContent('RAM')
    })
  })
})

describe('productCategoryColumnRenderers.products_count', () => {
  it('lists every product name when under the server cap', async () => {
    renderCell('products_count', 2, {
      products: [
        { id: 10, name: 'ThinkPad X1' },
        { id: 11, name: 'ThinkPad T14' },
      ],
    })

    fireEvent.pointerMove(screen.getByLabelText('ThinkPad X1, ThinkPad T14'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent('ThinkPad T14')
    })
    expect(screen.queryByText(/more/)).not.toBeInTheDocument()
  })

  it('appends a "+N more" line when the hydrated list was capped server-side', async () => {
    renderCell('products_count', 150, {
      products: [{ id: 10, name: 'ThinkPad X1' }],
    })

    fireEvent.pointerMove(screen.getByLabelText('ThinkPad X1, +149 more'))
    await waitFor(() => {
      const tooltip = screen.getByRole('tooltip')
      expect(tooltip).toHaveTextContent('ThinkPad X1')
      expect(tooltip).toHaveTextContent('+149 more')
    })
  })
})
