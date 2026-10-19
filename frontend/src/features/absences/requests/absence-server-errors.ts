import axios from 'axios'

const UNPROCESSABLE_STATUS = 422

/** The envelope's own message of a rejected request, when it carries one. */
export function serverMessage(error: unknown): string | null {
  const message = axios.isAxiosError(error)
    ? (error.response?.data as { message?: unknown } | undefined)?.message
    : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

/** First 422 message of one field of a rejected request, or null. */
export function fieldError(error: unknown, field: string): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== UNPROCESSABLE_STATUS) {
    return null
  }
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors
  return errors?.[field]?.[0] ?? null
}
