import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { InvoiceEditorAvailableLines } from '@/features/invoices/invoice-editor-available-lines'
import type { AvailableInvoiceLine, AvailableLineCommission } from '@/features/invoices/types'

const NO_LINES_ADDED: ReadonlySet<number> = new Set()

function line(id: number, commission: AvailableLineCommission | null): AvailableInvoiceLine {
  return {
    quote_line_id: id,
    product: { id, name: `Service ${id}` },
    description: `Service ${id}`,
    quantity: '1.00',
    unit_price: '100.00',
    vat_rate: null,
    net_amount: '100.00',
    vat_amount: '0.00',
    total_amount: '100.00',
    commission,
  }
}

function renderLines(lines: AvailableInvoiceLine[]) {
  render(<InvoiceEditorAvailableLines lines={lines} addedQuoteLineIds={NO_LINES_ADDED} onAdd={vi.fn()} onAddAll={vi.fn()} />)
}

const commissionHeader = () => screen.queryByRole('columnheader', { name: i18n.t('invoiceEditor.available.columns.commission') })

describe('InvoiceEditorAvailableLines commission (spec 0288, AC-012)', () => {
  it('shows the rule of each institution line and warns when none applies', () => {
    renderLines([
      line(1, { type: 'PERCENTAGE', value: '10.00', base_amount: '1000.00' }),
      line(2, { type: 'FIXED_AMOUNT', value: '150.00', base_amount: '300.00' }),
      line(3, { type: null, value: null, base_amount: '500.00' }),
    ])

    expect(commissionHeader()).toBeInTheDocument()
    expect(screen.getByText(/^10 %/)).toBeInTheDocument()
    expect(screen.getByText(i18n.t('invoiceEditor.available.commission.fixedAmount'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('invoiceEditor.available.commission.missing'))).toBeInTheDocument()
  })

  it('has no commission column on consultancy lines', () => {
    renderLines([line(1, null)])

    expect(commissionHeader()).not.toBeInTheDocument()
  })
})
