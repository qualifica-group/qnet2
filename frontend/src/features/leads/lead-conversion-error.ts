import axios from 'axios'
import type { LeadConversionBlocker, LeadConversionBlockedError } from '@/features/leads/types'

/** Discriminator of the 422 body a refused conversion answers with (spec 0071). */
const NOT_CONVERTIBLE = 'not_convertible'

/**
 * The blockers of a conversion refused by `POST /leads/convert-to-opportunities`,
 * or null for any other failure. Shared by the bulk dialog and the single-lead
 * conversion so both read the 422 body the same way.
 */
export function conversionBlockers(error: unknown): LeadConversionBlocker[] | null {
  const blocked = axios.isAxiosError(error)
    ? (error.response?.data?.errors as LeadConversionBlockedError | undefined)
    : undefined

  return blocked?.reason === NOT_CONVERTIBLE ? blocked.blockers : null
}
