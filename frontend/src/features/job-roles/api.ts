import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  JOB_ROLES_DOMAIN,
  type JobRoleDetail,
  type JobRoleDetailWithPermissions,
  type JobRolePayload,
  type UpdateJobRolePayload,
} from '@/features/job-roles/types'

const ENDPOINT = `/${JOB_ROLES_DOMAIN}`

/** Centralized query keys of the job-roles feature. */
export const jobRoleKeys = {
  all: [JOB_ROLES_DOMAIN] as const,
  detail: (id: number) => [JOB_ROLES_DOMAIN, 'detail', id] as const,
}

/** Permissions derived from the instance abilities, used when the envelope carries none. */
function permissionsFromAbilities(abilities: JobRoleDetail['abilities']): ResourcePermissions {
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

/** Fetches a job role together with the actor's authorization metadata for it. */
export async function fetchJobRole(id: number): Promise<JobRoleDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<JobRoleDetail, ResourcePermissions | undefined>>(
    `${ENDPOINT}/${id}`,
  )
  return { ...data.data, permissions: data.permissions ?? permissionsFromAbilities(data.data.abilities) }
}

export async function createJobRole(payload: JobRolePayload): Promise<JobRoleDetail> {
  const { data } = await apiClient.post<ApiResponse<JobRoleDetail>>(ENDPOINT, payload)
  return data.data
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateJobRole(id: number, payload: UpdateJobRolePayload): Promise<JobRoleDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<JobRoleDetail, ResourcePermissions | undefined>>(
    `${ENDPOINT}/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions ?? permissionsFromAbilities(data.data.abilities) }
}

/** 409 when the job role still has people: the caller maps it. */
export async function deleteJobRole(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
