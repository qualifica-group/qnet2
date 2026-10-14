import axios from 'axios'
import type { UseFormSetError } from 'react-hook-form'
import type { ReceiptFormValues } from '@/features/purchase-orders/purchase-order-receipt-schema'

const UNPROCESSABLE_STATUS = 422
const HEADER_FIELDS = ['receipt_date', 'delivery_note_number', 'delivery_note_date', 'notes'] as const
type HeaderField = (typeof HEADER_FIELDS)[number]
const LINE_ERROR_PATTERN = /^lines\.(\d+)\.(quantity|purchase_order_line_id)$/

function isHeaderField(key: string): key is HeaderField {
  return (HEADER_FIELDS as readonly string[]).includes(key)
}

/**
 * Puts each 422 error on its input and returns the messages that map to none.
 * The server numbers `lines.N` against the lines that were sent, so `rowIndexes`
 * maps N back to the dialog row (both the quantity and the line reference
 * errors surface on that row's quantity input). Returns `null` when the
 * failure is not a 422.
 */
export function applyReceiptServerErrors(
  error: unknown,
  setError: UseFormSetError<ReceiptFormValues>,
  rowIndexes: readonly number[],
): string[] | null {
  if (!axios.isAxiosError(error) || error.response?.status !== UNPROCESSABLE_STATUS) {
    return null
  }
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  const unmapped: string[] = []
  for (const [key, messages] of Object.entries(errors)) {
    const message = messages[0]
    if (!message) continue
    const lineMatch = LINE_ERROR_PATTERN.exec(key)
    const rowIndex = lineMatch ? rowIndexes[Number(lineMatch[1])] : undefined
    if (rowIndex !== undefined) {
      setError(`rows.${rowIndex}.quantity`, { message })
    } else if (isHeaderField(key)) {
      setError(key, { message })
    } else {
      unmapped.push(message)
    }
  }
  return unmapped
}
