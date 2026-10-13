import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  COMMISSION_SETTLEMENTS_DOMAIN,
  type AdjustmentCreatePayload,
  type AdjustmentUpdatePayload,
  type CommissionAdjustment,
  type SettlementCancelPayload,
  type SettlementDetail,
  type SettlementDetailWithPermissions,
  type SettlementNotesPayload,
} from '@/features/commission-settlements/types'

const ENDPOINT = `/${COMMISSION_SETTLEMENTS_DOMAIN}`
const ADJUSTMENTS_ENDPOINT = '/commission-adjustments'

/** Centralized query keys of the commission-settlements feature. */
export const commissionSettlementKeys = {
  all: [COMMISSION_SETTLEMENTS_DOMAIN] as const,
  detail: (id: number) => [COMMISSION_SETTLEMENTS_DOMAIN, 'detail', id] as const,
}

type DetailResponse = ApiResponseWithPermissions<SettlementDetail, ResourcePermissions>

function unwrap(response: DetailResponse): SettlementDetailWithPermissions {
  return { ...response.data, permissions: response.permissions }
}

export async function fetchCommissionSettlement(id: number): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.get<DetailResponse>(`${ENDPOINT}/${id}`)
  return unwrap(data)
}

export async function updateSettlementNotes(
  id: number,
  payload: SettlementNotesPayload,
): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.patch<DetailResponse>(`${ENDPOINT}/${id}`, payload)
  return unwrap(data)
}

export async function recalculateSettlement(id: number): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.post<DetailResponse>(`${ENDPOINT}/${id}/recalculate`)
  return unwrap(data)
}

export async function issueSettlement(id: number): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.post<DetailResponse>(`${ENDPOINT}/${id}/issue`)
  return unwrap(data)
}

export async function cancelSettlement(
  id: number,
  payload: SettlementCancelPayload,
): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.post<DetailResponse>(`${ENDPOINT}/${id}/cancel`, payload)
  return unwrap(data)
}

/** 204 without body; 409 when the settlement is not a draft. */
export async function deleteSettlement(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}

export async function createAdjustment(payload: AdjustmentCreatePayload): Promise<CommissionAdjustment> {
  const { data } = await apiClient.post<ApiResponse<CommissionAdjustment>>(ADJUSTMENTS_ENDPOINT, payload)
  return data.data
}

export async function updateAdjustment(
  id: number,
  payload: AdjustmentUpdatePayload,
): Promise<CommissionAdjustment> {
  const { data } = await apiClient.put<ApiResponse<CommissionAdjustment>>(`${ADJUSTMENTS_ENDPOINT}/${id}`, payload)
  return data.data
}

export async function deleteAdjustment(id: number): Promise<void> {
  await apiClient.delete(`${ADJUSTMENTS_ENDPOINT}/${id}`)
}
