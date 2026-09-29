import { apiClient } from '@/api/client'
import type {
  ForSelectItem,
  ForSelectParams,
  ForSelectResponse,
} from '@/features/for-select/types'

/** Default page size requested per for-select fetch (server caps at 100). */
export const FOR_SELECT_PAGE_SIZE = 25

/**
 * Fetches a page of options from a `GET /api/{resource}/for-select` endpoint.
 * Like the other paginated list endpoints, the response body is the paginated
 * payload directly (`{ items, pagination, ... }`), NOT wrapped in the standard
 * `{ success, message, data }` envelope (ADR 0011). `ids[]` is sent only when
 * non-empty to hydrate already-selected values in edit mode.
 */
export async function fetchForSelect(
  resource: string,
  params: ForSelectParams = {},
): Promise<ForSelectResponse<ForSelectItem>> {
  const { search, offset = 0, limit = FOR_SELECT_PAGE_SIZE, ids, includeTotal, params: extraParams } = params

  const { data } = await apiClient.get<ForSelectResponse<ForSelectItem>>(
    `/${resource}/for-select`,
    {
      params: {
        offset,
        limit,
        ...(search ? { search } : {}),
        ...(ids && ids.length > 0 ? { ids } : {}),
        ...(includeTotal === false ? { include_total: 0 } : {}),
        ...extraParams,
      },
      // Serialize `ids` as repeated `ids[]=1&ids[]=2` (Laravel array convention).
      paramsSerializer: { indexes: true },
    },
  )

  return data
}
