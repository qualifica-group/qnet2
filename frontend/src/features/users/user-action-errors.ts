import axios from 'axios'

/**
 * The backend's own words for a rejected user action: the first entry of the
 * `errors` bag (e.g. `user` on a resend to an activated account, the mandatory
 * business function on an activation), else the envelope `message`.
 */
export function backendErrorMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const data = error.response?.data as
    | { message?: string; errors?: Record<string, string[]> }
    | undefined
  const [firstError] = Object.values(data?.errors ?? {})
  return firstError?.[0] ?? data?.message ?? null
}
