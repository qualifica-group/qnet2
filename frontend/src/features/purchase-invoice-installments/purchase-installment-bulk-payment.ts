import { z } from 'zod'
import type { TFunction } from 'i18next'
import { collectedTotal, parseCollectedAmount } from '@/features/invoice-installments/installment-bulk-collection'
import { fromCents, toCents } from '@/lib/amounts/line-amounts'
import { formatEuro } from '@/features/invoices/invoice-format'
import { isGroupRow } from '@/features/table/row-grouping'
import type { TableRow } from '@/features/table/types'
import { purchaseDocumentLabel } from '@/features/purchase-invoice-installments/purchase-installment-labels'
import type { BulkPaymentPayload } from '@/features/purchase-invoice-installments/types'

/** Grouped column that turns the bulk payment on (spec 0211 D-8). */
export const BULK_PAYMENT_GROUP_COLUMN = 'supplier'
/** Permission needed to pay; the action key below is only offered to holders of it. */
export const PAY_PERMISSION = 'purchase-invoices.pay'
const PAY_ACTION = 'record_payment'

/** One selected installment as the bulk payment dialog shows it. */
export interface BulkPaymentTarget {
  id: number
  documentLabel: string
  sequence: number
  dueDate: string
  amount: number
}

/**
 * A leaf row the actor can pay (the server only offers `record_payment` on an
 * unpaid installment to a user holding purchase-invoices.pay) and whose amount
 * is visible: the dialog needs it as the per-row ceiling.
 */
export function isBulkPayable(row: TableRow): boolean {
  return !isGroupRow(row) && row.actions.includes(PAY_ACTION) && typeof row.amount === 'string'
}

/** True when every selected row belongs to the same supplier (D-8). */
export function hasSingleSupplier(rows: readonly TableRow[]): boolean {
  return new Set(rows.map((row) => row.supplier_id)).size <= 1
}

export function toBulkPaymentTargets(rows: readonly TableRow[]): BulkPaymentTarget[] {
  return rows.filter(isBulkPayable).map((row) => ({
    id: Number(row.id),
    documentLabel: purchaseDocumentLabel({
      id: Number(row.purchase_invoice_id),
      document_number: typeof row.document_number === 'string' ? row.document_number : null,
    }),
    sequence: Number(row.sequence),
    dueDate: String(row.due_date ?? ''),
    amount: Number(row.amount),
  }))
}

/** Amount parsing and cent-safe running total are direction-agnostic: shared with the receivable bulk dialog. */
export const parsePaidAmount = parseCollectedAmount
export const paidTotal = collectedTotal

export function selectedTotal(targets: readonly BulkPaymentTarget[]): number {
  return fromCents(targets.reduce((total, target) => total + toCents(target.amount), 0))
}

function amountError(t: TFunction, text: string, target: BulkPaymentTarget | undefined): string | null {
  if (text.trim() === '' || !target) {
    return null
  }
  const value = Number(text)
  if (Number.isNaN(value)) {
    return t('purchaseInvoiceInstallments.bulkPayment.errors.amountInvalid')
  }
  if (value < 0) {
    return t('purchaseInvoiceInstallments.bulkPayment.errors.amountNegative')
  }
  if (toCents(value) > toCents(target.amount)) {
    return t('purchaseInvoiceInstallments.bulkPayment.errors.amountExceeds', { amount: formatEuro(target.amount) })
  }
  return null
}

/** Amounts stay text (an empty field is legal); each one is checked against its own installment. */
export function buildBulkPaymentSchema(t: TFunction, targets: readonly BulkPaymentTarget[]) {
  return z
    .object({
      paid_at: z.string().min(1, t('purchaseInvoiceInstallments.bulkPayment.errors.dateRequired')),
      financial_account_id: z.number().int().nullable(),
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

export type BulkPaymentFormValues = z.infer<ReturnType<typeof buildBulkPaymentSchema>>

/**
 * The request body plus, for each sent item, the index of its dialog row:
 * the server keys its 422 by the sent position (`items.N.*`).
 */
export function buildBulkPaymentPayload(
  values: BulkPaymentFormValues,
  targets: readonly BulkPaymentTarget[],
): { payload: BulkPaymentPayload; rowIndexes: number[] } {
  const items: BulkPaymentPayload['items'] = []
  const rowIndexes: number[] = []
  values.amounts.forEach((text, index) => {
    const amount = parsePaidAmount(text)
    const target = targets[index]
    if (amount !== null && target) {
      items.push({ installment_id: target.id, paid_amount: fromCents(toCents(amount)) })
      rowIndexes.push(index)
    }
  })
  return {
    payload: { paid_at: values.paid_at, financial_account_id: values.financial_account_id, items },
    rowIndexes,
  }
}
