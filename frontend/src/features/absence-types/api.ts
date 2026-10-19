import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  ABSENCE_TYPES_DOMAIN,
  type AbsenceType,
  type AbsenceTypeDetail,
  type AbsenceTypePayload,
  type UpdateAbsenceTypePayload,
} from '@/features/absence-types/types'

export { absenceTypeKeys } from '@/features/absences/query-keys'

const ENDPOINT = `/${ABSENCE_TYPES_DOMAIN}`

/** Read-only fallback when the envelope carries no authorization metadata: the backend stays the authority. */
const VIEW_ONLY_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
  fields: {},
  actions: {},
}

type PermissionsEnvelope = ApiResponseWithPermissions<AbsenceType, ResourcePermissions | undefined>

function withPermissions({ data, permissions }: PermissionsEnvelope): AbsenceTypeDetail {
  return { ...data, permissions: permissions ?? VIEW_ONLY_PERMISSIONS }
}

/** Fetches an absence type together with the actor's authorization metadata for it. */
export async function fetchAbsenceType(id: number): Promise<AbsenceTypeDetail> {
  const { data } = await apiClient.get<PermissionsEnvelope>(`${ENDPOINT}/${id}`)
  return withPermissions(data)
}

export async function createAbsenceType(payload: AbsenceTypePayload): Promise<AbsenceType> {
  const { data } = await apiClient.post<ApiResponse<AbsenceType>>(ENDPOINT, payload)
  return data.data
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateAbsenceType(id: number, payload: UpdateAbsenceTypePayload): Promise<AbsenceTypeDetail> {
  const { data } = await apiClient.patch<PermissionsEnvelope>(`${ENDPOINT}/${id}`, payload)
  return withPermissions(data)
}

/** 409 when a request already uses the type: the caller maps it to "deactivate it instead". */
export async function deleteAbsenceType(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
