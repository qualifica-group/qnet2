import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'
import { JOB_ROLES_DOMAIN } from '@/features/job-roles/types'

/** Resource segment for the job-roles for-select endpoint. */
export const JOB_ROLES_FOR_SELECT_RESOURCE = JOB_ROLES_DOMAIN

/** Fetches a page of job role options from `GET /api/job-roles/for-select`. */
export function fetchJobRolesForSelect(params: ForSelectParams = {}): Promise<PaginatedResponse<ForSelectItem>> {
  return fetchForSelect(JOB_ROLES_FOR_SELECT_RESOURCE, params)
}

interface UseJobRolesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/** Hook feeding a job role single-select: debounced search, pagination and `ids[]` hydration. */
export function useJobRolesForSelect({ search, ids, enabled }: UseJobRolesForSelectOptions) {
  return useForSelect({ resource: JOB_ROLES_FOR_SELECT_RESOURCE, search, ids, enabled })
}
