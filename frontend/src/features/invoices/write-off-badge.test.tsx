import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { installmentColumnRenderers } from '@/features/invoice-installments/column-renderers'
import { purchaseInstallmentColumnRenderers } from '@/features/purchase-invoice-installments/column-renderers'
import { InvoiceInstallmentsTable } from '@/features/invoices/invoice-installments-table'
import { PurchaseInvoiceDetailInstallments } from '@/features/purchase-invoices/purchase-invoice-detail-installments'
import type { InvoiceInstallment, WriteOff } from '@/features/invoices/types'
import type { PurchaseInvoiceInstallment } from '@/features/purchase-invoices/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const ROUNDING: WriteOff = { amount: '0.20', reason: 'rounding', note: 'Cents' }
const BAD_DEBT: WriteOff = { amount: '50.00', reason: 'bad_debt', note: null }

function cell(renderer: (params: ICellRendererParams) => React.ReactNode, value: unknown, data: unknown) {
  return render(<>{renderer({ value, data } as unknown as ICellRendererParams)}</>)
}

describe('write-off label in the grids (spec 0361 AC-014)', () => {
  it.each([
    ['rounding', 'Write-off', '0.20'],
    ['bad_debt', 'Bad debt', '50.00'],
  ])('active grid: paid + %s label with amount', (reason, label, amount) => {
    cell(installmentColumnRenderers.status, 'paid', { write_off_reason: reason, write_off_amount: amount })
    expect(screen.getByText('Collected')).toBeInTheDocument()
    expect(screen.getByText(new RegExp(`^${label}`))).toBeInTheDocument()
  })

  it('passive grid: paid + bad debt label', () => {
    cell(purchaseInstallmentColumnRenderers.status, 'paid', { write_off_reason: 'bad_debt', write_off_amount: '50.00' })
    expect(screen.getByText(/^Bad debt/)).toBeInTheDocument()
  })

  it('no label without write-off', () => {
    cell(installmentColumnRenderers.status, 'paid', { write_off_reason: null, write_off_amount: null })
    cell(purchaseInstallmentColumnRenderers.status, 'paid', {})
    expect(screen.queryByText(/Write-off|Bad debt/)).not.toBeInTheDocument()
  })

  it('renders the read-only write-off columns', () => {
    cell(installmentColumnRenderers.write_off_reason, 'rounding', {})
    expect(screen.getByText('Write-off')).toBeInTheDocument()
  })
})

const ACTIVE: InvoiceInstallment = {
  id: 1,
  sequence: 1,
  due_date: '2026-11-05',
  amount: '1000.00',
  payment_method_code: null,
  collected_amount: '999.80',
  collected_at: '2026-11-06',
  status: 'paid',
}
const PASSIVE: PurchaseInvoiceInstallment = {
  id: 1,
  sequence: 1,
  due_date: '2026-11-05',
  amount: '1000.00',
  payment_method_code: null,
  paid_amount: '950.00',
  paid_at: '2026-11-06',
  status: 'paid',
}

describe('write-off label in the invoice detail (spec 0361 AC-014)', () => {
  it('active: label with the note as tooltip', () => {
    render(
      <InvoiceInstallmentsTable
        installments={[{ ...ACTIVE, write_off: ROUNDING }]}
        canCollect={false}
        busyInstallmentId={null}
        onRecord={() => undefined}
        onClear={() => undefined}
      />,
    )
    expect(screen.getByText(/^Write-off/)).toHaveAttribute('title', 'Cents')
  })

  it('active: no label without write_off', () => {
    render(
      <InvoiceInstallmentsTable
        installments={[ACTIVE]}
        canCollect={false}
        busyInstallmentId={null}
        onRecord={() => undefined}
        onClear={() => undefined}
      />,
    )
    expect(screen.queryByText(/Write-off|Bad debt/)).not.toBeInTheDocument()
  })

  it('passive: label for bad debt, none when null', () => {
    render(
      <PurchaseInvoiceDetailInstallments
        installments={[{ ...PASSIVE, write_off: BAD_DEBT }, { ...PASSIVE, id: 2, write_off: null }]}
        canPay={false}
        busyInstallmentId={null}
        onRecord={() => undefined}
        onClear={() => undefined}
      />,
    )
    expect(screen.getAllByText(/^Bad debt/)).toHaveLength(1)
  })
})
