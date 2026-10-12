import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildBulkPaymentPayload,
  buildBulkPaymentSchema,
  hasSingleSupplier,
  isBulkPayable,
  paidTotal,
  parsePaidAmount,
  selectedTotal,
  toBulkPaymentTargets,
  type BulkPaymentTarget,
} from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment'
import { GROUP_ROW_FLAG } from '@/features/table/row-grouping'
import type { TableRow } from '@/features/table/types'

const t = ((key: string) => key) as unknown as TFunction

function row(overrides: Partial<TableRow> = {}): TableRow {
  return {
    id: 1,
    actions: ['edit', 'record_payment'],
    supplier_id: 10,
    purchase_invoice_id: 3,
    document_number: 'F-12',
    sequence: 1,
    due_date: '2026-10-31',
    amount: '1000.00',
    ...overrides,
  }
}

const TARGETS: BulkPaymentTarget[] = [
  { id: 1, documentLabel: 'F-12', sequence: 1, dueDate: '2026-10-31', amount: 1000 },
  { id: 2, documentLabel: '#4', sequence: 1, dueDate: '2026-11-30', amount: 500 },
]

describe('bulk payment helpers (spec 0211 AC-017)', () => {
  it('only payable leaf rows with a visible amount can be selected', () => {
    expect(isBulkPayable(row())).toBe(true)
    expect(isBulkPayable(row({ actions: ['edit', 'clear_payment'] }))).toBe(false)
    expect(isBulkPayable(row({ actions: ['edit'] }))).toBe(false)
    expect(isBulkPayable(row({ amount: undefined }))).toBe(false)
    expect(isBulkPayable(row({ [GROUP_ROW_FLAG]: true }))).toBe(false)
  })

  it('tells whether the selection belongs to one supplier', () => {
    expect(hasSingleSupplier([row(), row({ id: 2 })])).toBe(true)
    expect(hasSingleSupplier([row(), row({ id: 2, supplier_id: 11 })])).toBe(false)
  })

  it('maps the selected rows onto the dialog targets, labelling a number-less document by its id', () => {
    expect(
      toBulkPaymentTargets([
        row(),
        row({ id: 2, purchase_invoice_id: 4, document_number: null, due_date: '2026-11-30', amount: '500.00' }),
      ]),
    ).toEqual(TARGETS)
  })

  it('empty, zero and non numeric amounts are not paid; the total sums only what would be sent', () => {
    expect(parsePaidAmount('')).toBeNull()
    expect(parsePaidAmount('0')).toBeNull()
    expect(parsePaidAmount('abc')).toBeNull()
    expect(parsePaidAmount('600.5')).toBe(600.5)
    expect(paidTotal(['600', '', '0', '500.10'])).toBe(1100.1)
    expect(selectedTotal(TARGETS)).toBe(1500)
  })

  it('an amount above its own installment is an error on that row only', () => {
    const result = buildBulkPaymentSchema(t, TARGETS).safeParse({
      paid_at: '2026-10-08',
      financial_account_id: null,
      amounts: ['1000.01', '500'],
    })

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => [issue.path.join('.'), issue.message])).toEqual([
      ['amounts.0', 'purchaseInvoiceInstallments.bulkPayment.errors.amountExceeds'],
    ])
  })

  it('accepts empty rows and the exact installment amount', () => {
    const values = { paid_at: '2026-10-08', financial_account_id: null, amounts: ['', '500'] }
    expect(buildBulkPaymentSchema(t, TARGETS).safeParse(values).success).toBe(true)
  })

  it('sends the contract payload with the account and only the filled rows, remembering their dialog position', () => {
    expect(
      buildBulkPaymentPayload({ paid_at: '2026-10-08', financial_account_id: 9, amounts: ['', '250.555'] }, TARGETS),
    ).toEqual({
      payload: {
        paid_at: '2026-10-08',
        financial_account_id: 9,
        items: [{ installment_id: 2, paid_amount: 250.56 }],
      },
      rowIndexes: [1],
    })
  })
})
