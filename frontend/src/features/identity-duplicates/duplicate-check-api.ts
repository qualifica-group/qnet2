import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'

/**
 * Contact types the duplicate check matches on (spec 0037): a deliberate
 * subset of the full contact type enum (`pec`/`website`/... exist, but only
 * these three are duplicate-check criteria, mirroring the import dedup rules
 * of spec 0036).
 */
export type IdentityDuplicateContactType = 'email' | 'phone' | 'mobile'

/** One contact criterion sent to the duplicate-check endpoint. */
export interface IdentityDuplicateContact {
  type: IdentityDuplicateContactType
  value: string
}

/** POST /identity/duplicate-check request body. At least one criterion is required server-side (422 otherwise). */
export interface IdentityDuplicateCheckPayload {
  tax_code?: string
  vat_number?: string
  contacts?: IdentityDuplicateContact[]
}

/** Morph alias of the record holding a matched value — the whole identity namespace, as the write gate. */
export type IdentityDuplicateOwnerType = 'user' | 'registry' | 'referent'

/**
 * One existing holder matching the submitted criteria. Deliberately carries
 * only `{ owner_type, owner_id, name, matched_on }` — never the raw contact
 * value, tax code or VAT number of the match, so the check cannot become a
 * PII exfiltration channel.
 */
export interface IdentityDuplicateMatch {
  owner_type: IdentityDuplicateOwnerType
  owner_id: number
  name: string
  matched_on: string[]
}

interface IdentityDuplicateCheckResponse {
  matches: IdentityDuplicateMatch[]
}

/**
 * Query key of a duplicate check for a given set of (already trimmed)
 * criteria, so identical inputs share the cache and a criteria change starts
 * a fresh request.
 */
export function identityDuplicateCheckQueryKey(
  taxCode: string,
  vatNumber: string,
  contacts: IdentityDuplicateContact[],
) {
  return ['identity', 'duplicate-check', { taxCode, vatNumber, contacts }] as const
}

/**
 * Checks whether the given fiscal identifiers / contacts already belong to a
 * user, an anagrafica or a referente. Read-only, non-blocking: the caller only
 * ever uses the result to render a warning, never to gate the save (the write
 * gate is server-side).
 */
export async function checkIdentityDuplicates(
  payload: IdentityDuplicateCheckPayload,
): Promise<IdentityDuplicateCheckResponse> {
  const { data } = await apiClient.post<ApiResponse<IdentityDuplicateCheckResponse>>(
    '/identity/duplicate-check',
    payload,
  )

  return data.data
}
