import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import { OdaLink } from '@/features/purchase-requests/oda-link'
import { OdaCell } from '@/features/purchase-requests/purchase-request-cells'

describe('ODA column (spec 0210 AC-022)', () => {
  it('links the ODA code to /purchase-orders/:id when the line is linked', () => {
    render(
      <MemoryRouter>
        <OdaLink purchaseOrder={{ id: 9, code: 'ODA-9' }} odaReference="ODA-9" />
      </MemoryRouter>,
    )
    expect(screen.getByRole('link', { name: 'ODA-9' })).toHaveAttribute('href', '/purchase-orders/9')
  })

  it('falls back to the reference text, then to a dash', () => {
    const { rerender } = render(
      <MemoryRouter>
        <OdaLink purchaseOrder={null} odaReference="LEGACY-1" />
      </MemoryRouter>,
    )
    expect(screen.getByText('LEGACY-1')).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    rerender(
      <MemoryRouter>
        <OdaLink purchaseOrder={null} />
      </MemoryRouter>,
    )
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('renders the grid cell as a link from the row purchase_order', () => {
    const params = { value: 'ODA-9', data: { purchase_order: { id: 9, code: 'ODA-9' } } } as ICellRendererParams
    render(
      <MemoryRouter>
        <OdaCell {...params} />
      </MemoryRouter>,
    )
    expect(screen.getByRole('link', { name: 'ODA-9' })).toHaveAttribute('href', '/purchase-orders/9')
  })
})
