import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CommissionConfigurationDetail,
  CommissionConfigurationDetailWithPermissions,
  CommissionConfigurationPayload,
  UpdateCommissionConfigurationPayload,
} from './types'

export const COMMISSION_CONFIGURATIONS_DOMAIN = 'commission-configurations'

export function commissionConfigurationDetailKey(id: number) {
  return [COMMISSION_CONFIGURATIONS_DOMAIN, 'detail', id] as const
}

export async function fetchCommissionConfiguration(
  id: number,
): Promise<CommissionConfigurationDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CommissionConfigurationDetail, ResourcePermissions>
  >(`/${COMMISSION_CONFIGURATIONS_DOMAIN}/${id}`)
  return { ...data.data, permissions: data.permissions }
}

export async function createCommissionConfiguration(
  payload: CommissionConfigurationPayload,
): Promise<CommissionConfigurationDetail> {
  const { data } = await apiClient.post<ApiResponse<CommissionConfigurationDetail>>(
    `/${COMMISSION_CONFIGURATIONS_DOMAIN}`,
    payload,
  )
  return data.data
}

/** Partial update; returns the saved record with the actor's permissions so the in-place detail re-syncs on it as is. */
export async function updateCommissionConfiguration(
  id: number,
  payload: UpdateCommissionConfigurationPayload,
): Promise<CommissionConfigurationDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<CommissionConfigurationDetail, ResourcePermissions>
  >(`/${COMMISSION_CONFIGURATIONS_DOMAIN}/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

export async function deleteCommissionConfiguration(id: number): Promise<void> {
  await apiClient.delete(`/${COMMISSION_CONFIGURATIONS_DOMAIN}/${id}`)
}
