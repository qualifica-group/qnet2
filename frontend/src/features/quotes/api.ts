import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateQuotePayload,
  QuoteDetail,
  QuoteDetailWithPermissions,
  UpdateQuotePayload,
} from '@/features/quotes/types'

/** Table/stats domain key of this module, shared by the table adapter. */
export const QUOTES_DOMAIN = 'quotes'

/**
 * Query key of a single quote's detail (fresh-on-open pattern). Shared by the
 * detail/edit pages and by the post-mutation invalidation, so they can never
 * drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function quoteDetailQueryKey(id: number | null) {
  return ['quotes', 'detail', id] as const
}

/**
 * Fetches a single quote detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchQuote(id: number): Promise<QuoteDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<QuoteDetail, ResourcePermissions>>(
    `/quotes/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/**
 * The next sequential code (`QUO-0001`...) suggested for the create form's
 * `code` auto-fill (D-13, mirrors `fetchProjectNextCode`/`fetchProductNextCode`).
 * Non-binding: the user may edit it, and the server resolves the definitive
 * value atomically on store. The caller must query this with
 * `staleTime: 0, gcTime: 0` (never cached) and only when `mode.type === 'create'`.
 */
export async function fetchQuoteNextCode(): Promise<string> {
  const { data } = await apiClient.get<ApiResponse<{ code: string }>>('/quotes/next-code')
  return data.data.code
}

/** Creates a quote. `code` falls back to server-side generation when omitted/empty. Returns the created resource. */
export async function createQuote(payload: CreateQuotePayload): Promise<QuoteDetail> {
  const { data } = await apiClient.post<ApiResponse<QuoteDetail>>('/quotes', payload)
  return data.data
}

/** Partially updates a quote (PATCH). Returns the updated resource. */
export async function updateQuote(id: number, payload: UpdateQuotePayload): Promise<QuoteDetail> {
  const { data } = await apiClient.patch<ApiResponse<QuoteDetail>>(`/quotes/${id}`, payload)
  return data.data
}

/** Deletes a quote. Backend responds 200 with no data (`quote_lines` cascade, AC-026). */
export async function deleteQuote(id: number): Promise<void> {
  await apiClient.delete(`/quotes/${id}`)
}
