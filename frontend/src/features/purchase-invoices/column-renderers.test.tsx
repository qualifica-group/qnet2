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

  it('reads the payslip type as Payslip in English and Cedolino in Italian (spec 0431 D-1)', async () => {
    const { unmount } = renderCell('document_type', 'payslip')
    expect(screen.getByText('Payslip')).toBeInTheDocument()
    unmount()

    await i18n.changeLanguage('it')
    renderCell('document_type', 'payslip')
    expect(screen.getByText('Cedolino')).toBeInTheDocument()
    await i18n.changeLanguage('en')
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
