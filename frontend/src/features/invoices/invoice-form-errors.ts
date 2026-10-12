import axios from 'axios'
import type { UseFormSetError } from 'react-hook-form'
import { isFormFieldPath } from '@/features/invoices/invoice-editor-lines'
import type { InvoiceFormValues } from '@/features/invoices/invoice-schema'

/** The envelope's own message of a rejected save, when it carries one. */
export function serverMessage(error: unknown): string | null {
  const message = axios.isAxiosError(error)
    ? (error.response?.data as { message?: unknown } | undefined)?.message
    : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

/** Puts each 422 field error on its input; true when at least one landed on a field. */
export function applyFieldErrors(error: unknown, setError: UseFormSetError<InvoiceFormValues>): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  let mapped = false
  for (const [key, messages] of Object.entries(errors)) {
    if (isFormFieldPath(key) && messages[0]) {
      setError(key, { message: messages[0] })
      mapped = true
    }
  }
  return mapped
}
