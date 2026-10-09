import axios from 'axios'
import type { FieldPath, UseFormSetError } from 'react-hook-form'
import { REQUEST_FIELD_KEYS } from '@/features/purchase-requests/purchase-request-permissions'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'

const LINE_FIELDS = ['product_id', 'description', 'reason', 'unit_of_measure_id', 'quantity', 'unit_price', 'vat_rate_id']
const LINE_FIELD_PATTERN = new RegExp(`^lines\\.\\d+\\.(${LINE_FIELDS.join('|')})$`)
const CONFLICT_STATUS = 409
const UNPROCESSABLE_STATUS = 422

function isFormFieldPath(key: string): key is FieldPath<PurchaseRequestFormValues> {
  return (REQUEST_FIELD_KEYS as readonly string[]).includes(key) || LINE_FIELD_PATTERN.test(key)
}

/** The envelope's own message of a rejected request, when it carries one. */
export function serverMessage(error: unknown): string | null {
  const message = axios.isAxiosError(error)
    ? (error.response?.data as { message?: unknown } | undefined)?.message
    : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

export function isConflict(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === CONFLICT_STATUS
}

/**
 * Puts each 422 error on its input and returns the messages that map to no
 * input (`lines`, `lines.N`: a row that is not editable or deletable) so the
 * form can list them. Returns `null` when the failure is not a 422.
 */
export function applyServerErrors(
  error: unknown,
  setError: UseFormSetError<PurchaseRequestFormValues>,
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
