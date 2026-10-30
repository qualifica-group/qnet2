import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'

/** First server-side message for a payment dialog, else the generic one. */
export function paymentServerMessage(error: AxiosError<ApiErrorResponse>, fallback: string): string {
  const fieldErrors = error.response?.data?.errors
  const first = fieldErrors ? Object.values(fieldErrors)[0]?.[0] : undefined
  return first ?? error.response?.data?.message ?? fallback
}
