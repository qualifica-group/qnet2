import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { ActivityLogEventFilter, ActivityLogPage } from '@/features/activity-log/types'

/** Default page size requested when the caller does not override it. */
export const ACTIVITY_LOG_DEFAULT_PAGE_SIZE = 25

/**
 * Fetches one keyset page of the aggregated activity log for a resource
 * record (spec 0034). `cursor` is the opaque token from the previous page's
 * `next_cursor`; omit it for the first page. `event` narrows the feed to a
 * single operation type — `'all'` omits the param, which is what the backend
 * reads as "every event".
 */
export async function fetchActivityLog(
  resource: string,
  id: number,
  cursor: string | null = null,
  perPage: number = ACTIVITY_LOG_DEFAULT_PAGE_SIZE,
  event: ActivityLogEventFilter = 'all',
): Promise<ActivityLogPage> {
  const { data } = await apiClient.get<ApiResponse<ActivityLogPage>>(
    `/activity-log/${resource}/${id}`,
    {
      params: {
        cursor: cursor ?? undefined,
        per_page: perPage,
        event: event === 'all' ? undefined : event,
      },
    },
  )
  return data.data
}
