import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { purchaseInstallmentColumnRenderers } from '@/features/purchase-invoice-installments/column-renderers'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('purchase installments grid - cost type column (spec 0249 AC-007)', () => {
  it('renders the cost type as a badge carrying its color', () => {
    const params = { value: { name: 'Fitti', color: 'blue' } } as unknown as ICellRendererParams
    const { container } = render(<>{purchaseInstallmentColumnRenderers.cost_type(params)}</>)

    expect(screen.getByText('Fitti')).toBeInTheDocument()
    const dot = container.querySelector('[aria-hidden="true"]')
    for (const className of (swatchClassFor('blue') ?? '').split(' ')) {
      expect(dot).toHaveClass(className)
    }
  })
})
