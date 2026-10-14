import { z } from 'zod'
import type { TFunction } from 'i18next'
import { todayIso } from '@/features/purchase-requests/purchase-request-payload'
import type { PurchaseOrderLine, PurchaseOrderReceiptPayload } from '@/features/purchase-orders/types'

/**
 * Zod schema of the "Registra ricezione" dialog, mirroring the frozen rules of
 * spec 0254 (date not in the future, notes/DDT limits, quantity 3 decimals and
 * not above the residual, at least one line). The server stays authoritative.
 */

const QUANTITY_DECIMALS = 3
const QUANTITY_SCALE = 10 ** QUANTITY_DECIMALS
const QUANTITY_PATTERN = /^\d+(\.\d{1,3})?$/
const DELIVERY_NOTE_NUMBER_MAX = 50
const NOTES_MAX = 2000

/** Quantity in thousandths, so comparisons stay on integers. */
function toMilli(value: string): number {
  return Math.round(Number(value) * QUANTITY_SCALE)
}

/** A blank or zero quantity skips the line. */
export function isSkippedQuantity(value: string): boolean {
  const trimmed = value.trim()
  return trimmed === '' || (QUANTITY_PATTERN.test(trimmed) && toMilli(trimmed) === 0)
}

export function formatQuantity(value: string | number): string {
  return Number(value).toFixed(QUANTITY_DECIMALS)
}

/** True when the line can still receive goods: not closed and with a positive residual. */
export function isReceivable(line: PurchaseOrderLine): boolean {
  return line.residual_closed === null && toMilli(line.residual_quantity) > 0
}

export function buildReceiptSchema(t: TFunction) {
  const rowSchema = z.object({
    purchase_order_line_id: z.number().int(),
    position: z.number().int(),
    description: z.string(),
    /** Context for the validation and the "Ricevi tutto" reset; never sent. */
    residual: z.string(),
    quantity: z.string(),
  })

  return z
    .object({
      receipt_date: z
        .string()
        .min(1, t('purchaseOrderActions.receipts.errors.dateRequired'))
        .refine((value) => value === '' || value <= todayIso(), t('purchaseOrderActions.receipts.errors.dateFuture')),
      delivery_note_number: z
        .string()
        .max(DELIVERY_NOTE_NUMBER_MAX, t('purchaseOrderActions.receipts.errors.deliveryNoteMax')),
      delivery_note_date: z.string(),
      notes: z.string().max(NOTES_MAX, t('purchaseOrderActions.receipts.errors.notesMax')),
      rows: z.array(rowSchema),
    })
    .superRefine((values, ctx) => {
      let received = 0
      let invalid = 0
      values.rows.forEach((row, index) => {
        const quantity = row.quantity.trim()
        if (isSkippedQuantity(quantity)) return
        const path = ['rows', index, 'quantity']
        if (!QUANTITY_PATTERN.test(quantity)) {
          invalid += 1
          ctx.addIssue({ code: 'custom', path, message: t('purchaseOrderActions.receipts.errors.quantityInvalid') })
        } else if (toMilli(quantity) > toMilli(row.residual)) {
          invalid += 1
          ctx.addIssue({
            code: 'custom',
            path,
            message: t('purchaseOrderActions.receipts.errors.quantityMax', { max: formatQuantity(row.residual) }),
          })
        } else {
          received += 1
        }
      })
      if (received === 0 && invalid === 0) {
        ctx.addIssue({ code: 'custom', path: ['rows'], message: t('purchaseOrderActions.receipts.errors.linesRequired') })
      }
    })
}

export type ReceiptFormValues = z.infer<ReturnType<typeof buildReceiptSchema>>
export type ReceiptRowValues = ReceiptFormValues['rows'][number]

/** One row per receivable line, quantity prefilled to the residual. */
export function receiptRowsFor(lines: readonly PurchaseOrderLine[]): ReceiptRowValues[] {
  return lines.filter(isReceivable).map((line) => ({
    purchase_order_line_id: line.id,
    position: line.position,
    description: line.description,
    residual: line.residual_quantity,
    quantity: formatQuantity(line.residual_quantity),
  }))
}

export function emptyReceiptValues(lines: readonly PurchaseOrderLine[], now: Date = new Date()): ReceiptFormValues {
  return {
    receipt_date: todayIso(now),
    delivery_note_number: '',
    delivery_note_date: '',
    notes: '',
    rows: receiptRowsFor(lines),
  }
}

/**
 * The request body plus, for each sent line, the index of its form row: the
 * server reports `lines.N` against the sent list, which skips the zero rows.
 */
export function buildReceiptPayload(values: ReceiptFormValues): {
  payload: PurchaseOrderReceiptPayload
  rowIndexes: number[]
} {
  const rowIndexes: number[] = []
  const lines: PurchaseOrderReceiptPayload['lines'] = []
  values.rows.forEach((row, index) => {
    if (isSkippedQuantity(row.quantity)) return
    rowIndexes.push(index)
    lines.push({ purchase_order_line_id: row.purchase_order_line_id, quantity: formatQuantity(row.quantity) })
  })
  return {
    payload: {
      receipt_date: values.receipt_date,
      delivery_note_number: values.delivery_note_number.trim() || null,
      delivery_note_date: values.delivery_note_date || null,
      notes: values.notes.trim() || null,
      lines,
    },
    rowIndexes,
  }
}
