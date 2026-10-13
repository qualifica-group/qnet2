import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  ENASARCO_PARAMETERS_DOMAIN,
  type EnasarcoParameterDetail,
  type EnasarcoParameterDetailWithPermissions,
  type EnasarcoParameterPayload,
} from '@/features/enasarco-parameters/types'

const ENDPOINT = `/${ENASARCO_PARAMETERS_DOMAIN}`

/** Centralized query keys of the enasarco-parameters feature. */
export const enasarcoParameterKeys = {
  all: [ENASARCO_PARAMETERS_DOMAIN] as const,
  detail: (id: number) => [ENASARCO_PARAMETERS_DOMAIN, 'detail', id] as const,
}

/** Fetches a parameter set together with the actor's authorization metadata for it. */
export async function fetchEnasarcoParameter(id: number): Promise<EnasarcoParameterDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<EnasarcoParameterDetail, ResourcePermissions>>(
    `${ENDPOINT}/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

export async function createEnasarcoParameter(payload: EnasarcoParameterPayload): Promise<EnasarcoParameterDetail> {
  const { data } = await apiClient.post<ApiResponse<EnasarcoParameterDetail>>(ENDPOINT, payload)
  return data.data
}

export async function updateEnasarcoParameter(
  id: number,
  payload: EnasarcoParameterPayload,
): Promise<EnasarcoParameterDetail> {
  const { data } = await apiClient.put<ApiResponse<EnasarcoParameterDetail>>(`${ENDPOINT}/${id}`, payload)
  return data.data
}

/** 204 without body; 409 when the year is used by an issued settlement. */
export async function deleteEnasarcoParameter(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
