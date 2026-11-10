import axios from 'axios'

/**
 * Ceiling on the two requests the app cannot start without (`GET /config`,
 * `GET /auth/me`). Without it a saturated server leaves the request hanging and
 * the boot splash or loader up forever; with it the user gets a Retry screen.
 * Only these requests carry it: exports and imports legitimately run longer.
 */
export const BOOT_REQUEST_TIMEOUT_MS = 10_000

/** Whether the request was aborted by its client-side `timeout`. */
export function isTimeoutError(error: unknown): boolean {
  return axios.isAxiosError(error) && (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT')
}
