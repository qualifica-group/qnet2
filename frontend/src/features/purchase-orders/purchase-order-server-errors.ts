import axios from 'axios'
import type { FieldPath, UseFormSetError } from 'react-hook-form'
import { ORDER_FIELD_KEYS } from '@/features/purchase-orders/purchase-order-permissions'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'

const LINE_FIELDS = [
  'product_id',
  'description',
  'cost_type_id',
  'unit_of_measure_id',
  'quantity',
  'unit_price',
  'vat_rate_id',
  'work_order_id',
]
const LINE_FIELD_PATTERN = new RegExp(`^lines\\.\\d+\\.(${LINE_FIELDS.join('|')})$`)
const UNPROCESSABLE_STATUS = 422

function isFormFieldPath(key: string): key is FieldPath<PurchaseOrderFormValues> {
  return (ORDER_FIELD_KEYS as readonly string[]).includes(key) || LINE_FIELD_PATTERN.test(key)
}

/**
 * Puts each 422 error on its input and returns the messages that map to no
 * input (`lines`, `lines.N`, `lines.N.id`) so the form can list them. Returns
 * `null` when the failure is not a 422. `serverMessage`/`isConflict` of the
 * RDA module cover the other statuses.
 */
export function applyOrderServerErrors(
  error: unknown,
  setError: UseFormSetError<PurchaseOrderFormValues>,
): string[] | null {
  if (!axios.isAxiosError(error) || error.response?.status !== UNPROCESSABLE_STATUS) {
    return null
  }
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  const unmapped: string[] = []
  for (const [key, messages] of Object.entries(errors)) {
    const message = messages[0]
    if (!message) continue
    if (isFormFieldPath(key)) {
      setError(key, { message })
    } else {
      unmapped.push(message)
    }
  }
  return unmapped
}
