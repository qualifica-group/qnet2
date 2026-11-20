import axios from 'axios'
import type { ApiErrorResponse } from '@/api/types'

/** The server's own sentence for a failed call (422/502 carry a user-facing reason), else the caller's fallback. */
export function serverMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError<Partial<ApiErrorResponse>>(error)) {
    const message = error.response?.data?.message
    if (typeof message === 'string' && message.trim() !== '') {
      return message.trim()
    }
  }
  return fallback
}
