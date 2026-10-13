import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { purchaseInvoiceColumnRenderers } from '@/features/purchase-invoices/column-renderers'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderCell(id: keyof typeof purchaseInvoiceColumnRenderers, value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  return render(<>{purchaseInvoiceColumnRenderers[id](params)}</>)
}

describe('purchase invoices grid renderers (spec 0250 AC-013)', () => {
  it('renders the document type as a translated badge', () => {
    renderCell('document_type', 'proforma')

    expect(screen.getByText('Proforma')).toBeInTheDocument()
  })

  it('renders the derived payment status with the active-invoice labels', () => {
    renderCell('payment_status', 'overdue')

    expect(screen.getByText('Overdue')).toBeInTheDocument()
  })

  it('formats the money columns in euro', () => {
    renderCell('total_amount', '366.00')

    expect(screen.getByText(/366,00/)).toBeInTheDocument()
  })
})
