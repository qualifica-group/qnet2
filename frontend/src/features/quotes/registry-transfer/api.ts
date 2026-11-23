import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** Body of `POST {basePath}/{id}/transfer-registry` (spec 0404, part B). */
export interface QuoteRegistryTransferPayload {
  registry_id: number
  /** Spec 0404 B-D8: absent = untouched, blank = back to the automatic title. */
  title?: string
  attribute_values?: Record<string, CustomFieldValue>
}

/**
 * Moves the offer to another anagrafica. `basePath` is `/quotes` or the
 * request module's own (`/request-management`, `/enrollee-management`): each
 * route authorizes with its module's permission set. The caller reloads its
 * record afterwards, so the answer body is not read.
 */
export async function transferQuoteRegistry(
  basePath: string,
  quoteId: number,
  payload: QuoteRegistryTransferPayload,
): Promise<string> {
  const { data } = await apiClient.post<ApiResponse<unknown>>(`${basePath}/${quoteId}/transfer-registry`, payload)
  return data.message
}
