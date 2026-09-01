import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { productColumnRenderers } from '@/features/products/column-renderers'

/** Spec 0017 AC-025: the `category` cell shows the category name; decimals render formatted. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderCell(columnId: string, value: unknown) {
  const renderer = productColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('productColumnRenderers.category', () => {
  it('renders the category name', () => {
    renderCell('category', { id: 3, name: 'Laptops' })
    expect(screen.getByText('Laptops')).toBeInTheDocument()
  })

  it('renders an em dash when the product has no category', () => {
    renderCell('category', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('productColumnRenderers.unit_of_measure', () => {
  it('badges the symbol and labels the info trigger with the unit name', () => {
    renderCell('unit_of_measure', { id: 2, name: 'Kilogram', symbol: 'kg' })

    expect(screen.getByText('kg')).toBeInTheDocument()
    expect(screen.queryByText('Kilogram')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Kilogram' })).toBeInTheDocument()
  })

  it('reveals the unit name in the tooltip', async () => {
    renderCell('unit_of_measure', { id: 2, name: 'Kilogram', symbol: 'kg' })

    fireEvent.pointerMove(screen.getByRole('button', { name: 'Kilogram' }))

    await waitFor(() => {
      expect(screen.getAllByText('Kilogram').length).toBeGreaterThan(0)
    })
  })

  it('renders an em dash when the product has no unit', () => {
    renderCell('unit_of_measure', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('productColumnRenderers.cost / price', () => {
  it('formats a decimal amount to two fraction digits', () => {
    renderCell('cost', 800)
    expect(screen.getByText('800.00')).toBeInTheDocument()
  })

  it('renders an em dash when null', () => {
    renderCell('price', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
