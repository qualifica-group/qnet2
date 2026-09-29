import axios from 'axios'

const HTTP_FORBIDDEN = 403
const HTTP_NOT_FOUND = 404

/** Why a record cannot be shown: it does not exist, or the actor may not see it. */
export type RecordUnavailableReason = 'notFound' | 'forbidden'

/**
 * Maps a failed record fetch to a "record unavailable" reason. Only 404/403
 * qualify: both are deterministic answers of the server, so a retry cannot
 * change them. Anything else (network error, 5xx) returns `null` and keeps the
 * caller's generic "could not load + retry" state.
 */
export function recordUnavailableReason(error: unknown): RecordUnavailableReason | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const status = error.response?.status
  if (status === HTTP_NOT_FOUND) {
    return 'notFound'
  }
  return status === HTTP_FORBIDDEN ? 'forbidden' : null
}
