import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  JOB_ACTIVITIES_DOMAIN,
  type JobActivityDetail,
  type JobActivityDetailWithPermissions,
  type JobActivityPayload,
  type UpdateJobActivityPayload,
} from '@/features/job-activities/types'

const ENDPOINT = `/${JOB_ACTIVITIES_DOMAIN}`

/** Centralized query keys of the job-activities feature. */
export const jobActivityKeys = {
  all: [JOB_ACTIVITIES_DOMAIN] as const,
  detail: (id: number) => [JOB_ACTIVITIES_DOMAIN, 'detail', id] as const,
}

/** Permissions derived from the instance abilities, used when the envelope carries none. */
function permissionsFromAbilities(abilities: JobActivityDetail['abilities']): ResourcePermissions {
  return {
    resource: {
      view: true,
      create: false,
      update: abilities.update,
      delete: abilities.delete,
      export: false,
      import: false,
    },
    fields: {},
    actions: {},
  }
}

/** Fetches a activity together with the actor's authorization metadata for it. */
export async function fetchJobActivity(id: number): Promise<JobActivityDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<JobActivityDetail, ResourcePermissions | undefined>>(
    `${ENDPOINT}/${id}`,
  )
  return { ...data.data, permissions: data.permissions ?? permissionsFromAbilities(data.data.abilities) }
}

export async function createJobActivity(payload: JobActivityPayload): Promise<JobActivityDetail> {
  const { data } = await apiClient.post<ApiResponse<JobActivityDetail>>(ENDPOINT, payload)
  return data.data
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateJobActivity(id: number, payload: UpdateJobActivityPayload): Promise<JobActivityDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<JobActivityDetail, ResourcePermissions | undefined>>(
    `${ENDPOINT}/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions ?? permissionsFromAbilities(data.data.abilities) }
}

/** 409 when the activity is linked to a job role: the caller maps it. */
export async function deleteJobActivity(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
