import { z } from 'zod'
import type { TFunction } from 'i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { isGroupRow } from '@/features/table/row-grouping'
import type { TableRow } from '@/features/table/types'
import type { BulkCollectionPayload } from '@/features/invoice-installments/types'

/** Grouped column that turns the bulk collection on (spec 0198 D-1). */
export const BULK_COLLECTION_GROUP_COLUMN = 'customer'
const COLLECT_ACTION = 'record_collection'
const CENTS = 100

/** One selected installment as the bulk collection dialog shows it. */
export interface BulkCollectionTarget {
  id: number
  invoiceLabel: string
  sequence: number
  dueDate: string
  amount: number
}

/**
 * A leaf row the actor can collect (the server only offers `record_collection`
 * on a not collected installment to a user holding invoices.collect) and whose
 * amount is visible: the dialog needs it as the per-row ceiling.
 */
export function isBulkCollectable(row: TableRow): boolean {
  return !isGroupRow(row) && row.actions.includes(COLLECT_ACTION) && typeof row.amount === 'string'
}

/** True when every selected row belongs to the same customer (D-2). */
export function hasSingleCustomer(rows: readonly TableRow[]): boolean {
  return new Set(rows.map((row) => row.customer_id)).size <= 1
}

export function toBulkCollectionTargets(rows: readonly TableRow[]): BulkCollectionTarget[] {
  return rows.filter(isBulkCollectable).map((row) => ({
    id: Number(row.id),
    invoiceLabel: String(row.invoice_number_label ?? ''),
    sequence: Number(row.sequence),
    dueDate: String(row.due_date ?? ''),
    amount: Number(row.amount),
  }))
}

const toCents = (value: number): number => Math.round(value * CENTS)

/** The typed amount, or null for an empty / zero / non numeric field (D-3: not collected). */
export function parseCollectedAmount(text: string): number | null {
  const value = Number(text)
  return text.trim() === '' || Number.isNaN(value) || toCents(value) <= 0 ? null : value
}

/** Sum of the amounts that would be sent, in cents to avoid float drift. */
export function collectedTotal(amounts: readonly string[]): number {
  return amounts.reduce((total, text) => total + toCents(parseCollectedAmount(text) ?? 0), 0) / CENTS
}

export function selectedTotal(targets: readonly BulkCollectionTarget[]): number {
  return targets.reduce((total, target) => total + toCents(target.amount), 0) / CENTS
}

/** Amounts stay text (an empty field is legal); each one is checked against its own installment (D-4). */
export function buildBulkCollectionSchema(t: TFunction, targets: readonly BulkCollectionTarget[]) {
  return z
    .object({
      collected_at: z.string().min(1, t('invoiceInstallments.bulkCollection.errors.dateRequired')),
      amounts: z.array(z.string()),
    })
    .superRefine((values, ctx) => {
      values.amounts.forEach((text, index) => {
        const message = amountError(t, text, targets[index])
        if (message) {
          ctx.addIssue({ code: 'custom', path: ['amounts', index], message })
        }
      })
    })
}

export type BulkCollectionFormValues = z.infer<ReturnType<typeof buildBulkCollectionSchema>>

function amountError(t: TFunction, text: string, target: BulkCollectionTarget | undefined): string | null {
  if (text.trim() === '' || !target) {
    return null
  }
  const value = Number(text)
  if (Number.isNaN(value)) {
    return t('invoiceInstallments.bulkCollection.errors.amountInvalid')
  }
  if (value < 0) {
    return t('invoiceInstallments.bulkCollection.errors.amountNegative')
  }
  if (toCents(value) > toCents(target.amount)) {
    return t('invoiceInstallments.bulkCollection.errors.amountExceeds', { amount: formatEuro(target.amount) })
  }
  return null
}

/**
 * The request body plus, for each sent item, the index of its dialog row:
 * the server keys its 422 by the sent position (`items.N.*`).
 */
export function buildBulkCollectionPayload(
  values: BulkCollectionFormValues,
  targets: readonly BulkCollectionTarget[],
): { payload: BulkCollectionPayload; rowIndexes: number[] } {
  const items: BulkCollectionPayload['items'] = []
  const rowIndexes: number[] = []
  values.amounts.forEach((text, index) => {
    const amount = parseCollectedAmount(text)
    const target = targets[index]
    if (amount !== null && target) {
      items.push({ installment_id: target.id, collected_amount: toCents(amount) / CENTS })
      rowIndexes.push(index)
    }
  })
  return { payload: { collected_at: values.collected_at, items }, rowIndexes }
}
