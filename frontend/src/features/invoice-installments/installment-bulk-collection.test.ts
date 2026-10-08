import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildBulkCollectionPayload,
  buildBulkCollectionSchema,
  collectedTotal,
  hasSingleCustomer,
  isBulkCollectable,
  parseCollectedAmount,
  toBulkCollectionTargets,
  type BulkCollectionTarget,
} from '@/features/invoice-installments/installment-bulk-collection'
import { GROUP_ROW_FLAG } from '@/features/table/row-grouping'
import type { TableRow } from '@/features/table/types'

const t = ((key: string) => key) as unknown as TFunction

function row(overrides: Partial<TableRow> = {}): TableRow {
  return {
    id: 1,
    actions: ['view_invoice', 'record_collection'],
    customer_id: 10,
    invoice_number_label: '12/2026',
    sequence: 1,
    due_date: '2026-10-31',
    amount: '1000.00',
    ...overrides,
  }
}

const TARGETS: BulkCollectionTarget[] = [
  { id: 1, invoiceLabel: '12/2026', sequence: 1, dueDate: '2026-10-31', amount: 1000 },
  { id: 2, invoiceLabel: '15/2026', sequence: 1, dueDate: '2026-11-30', amount: 500 },
]

describe('bulk collection helpers (spec 0198)', () => {
  it('D-1: only collectable leaf rows with a visible amount can be selected', () => {
    expect(isBulkCollectable(row())).toBe(true)
    expect(isBulkCollectable(row({ actions: ['view_invoice', 'clear_collection'] }))).toBe(false)
    expect(isBulkCollectable(row({ amount: undefined }))).toBe(false)
    expect(isBulkCollectable(row({ [GROUP_ROW_FLAG]: true }))).toBe(false)
  })

  it('D-2: tells whether the selection belongs to one customer', () => {
    expect(hasSingleCustomer([row(), row({ id: 2 })])).toBe(true)
    expect(hasSingleCustomer([row(), row({ id: 2, customer_id: 11 })])).toBe(false)
  })

  it('maps the selected rows onto the dialog targets', () => {
    expect(toBulkCollectionTargets([row(), row({ id: 2, invoice_number_label: '15/2026', due_date: '2026-11-30', amount: '500.00' })])).toEqual(TARGETS)
  })

  it('D-3: empty, zero and non numeric amounts are not collected', () => {
    expect(parseCollectedAmount('')).toBeNull()
    expect(parseCollectedAmount('0')).toBeNull()
    expect(parseCollectedAmount('abc')).toBeNull()
    expect(parseCollectedAmount('600.5')).toBe(600.5)
  })

  it('D-8: the footer total sums only the amounts that would be sent', () => {
    expect(collectedTotal(['600', '', '0', '500.10'])).toBe(1100.1)
    expect(collectedTotal(['0.1', '0.2'])).toBe(0.3)
  })

  it('D-4: an amount above its own installment is an error on that row only', () => {
    const result = buildBulkCollectionSchema(t, TARGETS).safeParse({ collected_at: '2026-10-08', amounts: ['1000.01', '500'] })

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => [issue.path.join('.'), issue.message])).toEqual([
      ['amounts.0', 'invoiceInstallments.bulkCollection.errors.amountExceeds'],
    ])
  })

  it('accepts empty rows and the exact installment amount', () => {
    expect(buildBulkCollectionSchema(t, TARGETS).safeParse({ collected_at: '2026-10-08', amounts: ['', '500'] }).success).toBe(true)
  })

  it('sends only the filled rows and remembers their dialog position', () => {
    expect(buildBulkCollectionPayload({ collected_at: '2026-10-08', amounts: ['', '250.555'] }, TARGETS)).toEqual({
      payload: { collected_at: '2026-10-08', items: [{ installment_id: 2, collected_amount: 250.56 }] },
      rowIndexes: [1],
    })
  })
})
