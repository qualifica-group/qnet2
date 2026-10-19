import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  STAFF_COURSES_DOMAIN,
  type StaffCourse,
  type StaffCourseDetail,
  type StaffCoursePayload,
  type UpdateStaffCoursePayload,
} from '@/features/staff-courses/types'

const ENDPOINT = `/${STAFF_COURSES_DOMAIN}`

/** Centralized query keys of the staff courses catalogue. */
export const staffCourseKeys = {
  all: [STAFF_COURSES_DOMAIN] as const,
  detail: (id: number) => [STAFF_COURSES_DOMAIN, 'detail', id] as const,
}

/** Read-only fallback when the envelope carries no authorization metadata: the backend stays the authority. */
const VIEW_ONLY_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
  fields: {},
  actions: {},
}

type PermissionsEnvelope = ApiResponseWithPermissions<StaffCourse, ResourcePermissions | undefined>

function withPermissions({ data, permissions }: PermissionsEnvelope): StaffCourseDetail {
  return { ...data, permissions: permissions ?? VIEW_ONLY_PERMISSIONS }
}

/** Fetches a course together with the actor's authorization metadata for it. */
export async function fetchStaffCourse(id: number): Promise<StaffCourseDetail> {
  const { data } = await apiClient.get<PermissionsEnvelope>(`${ENDPOINT}/${id}`)
  return withPermissions(data)
}

export async function createStaffCourse(payload: StaffCoursePayload): Promise<StaffCourse> {
  const { data } = await apiClient.post<ApiResponse<StaffCourse>>(ENDPOINT, payload)
  return data.data
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateStaffCourse(id: number, payload: UpdateStaffCoursePayload): Promise<StaffCourseDetail> {
  const { data } = await apiClient.patch<PermissionsEnvelope>(`${ENDPOINT}/${id}`, payload)
  return withPermissions(data)
}

/** 422 when the course already has trainings: the caller maps it to "deactivate it instead". */
export async function deleteStaffCourse(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
