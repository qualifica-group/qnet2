import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'
import { JOB_ACTIVITIES_DOMAIN } from '@/features/job-activities/types'

/** Resource segment for the job-activities for-select endpoint. */
export const JOB_ACTIVITIES_FOR_SELECT_RESOURCE = JOB_ACTIVITIES_DOMAIN

/** Fetches a page of jobActivity options from `GET /api/job-activities/for-select`. */
export function fetchJobActivitiesForSelect(params: ForSelectParams = {}): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(JOB_ACTIVITIES_FOR_SELECT_RESOURCE, params)
}

interface UseJobActivitiesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/** Hook feeding a activity single-select: debounced search, pagination and `ids[]` hydration. */
export function useJobActivitiesForSelect({ search, ids, enabled }: UseJobActivitiesForSelectOptions) {
  return useForSelect({ resource: JOB_ACTIVITIES_FOR_SELECT_RESOURCE, search, ids, enabled })
}
