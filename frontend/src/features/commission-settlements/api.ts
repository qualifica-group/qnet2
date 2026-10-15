import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  COMMISSION_SETTLEMENTS_DOMAIN,
  type AdjustmentCreatePayload,
  type AdjustmentUpdatePayload,
  type ApprovalDraft,
  type CommissionAdjustment,
  type SettlementApprovePayload,
  type SettlementCancelPayload,
  type SettlementDetail,
  type SettlementDetailWithPermissions,
  type SettlementDocumentPayload,
  type SettlementNotesPayload,
  type SettlementSupplierPayload,
} from '@/features/commission-settlements/types'

const ENDPOINT = `/${COMMISSION_SETTLEMENTS_DOMAIN}`
const ADJUSTMENTS_ENDPOINT = '/commission-adjustments'

/** Centralized query keys of the commission-settlements feature. */
export const commissionSettlementKeys = {
  all: [COMMISSION_SETTLEMENTS_DOMAIN] as const,
  detail: (id: number) => [COMMISSION_SETTLEMENTS_DOMAIN, 'detail', id] as const,
  approvalDraft: (id: number) => [COMMISSION_SETTLEMENTS_DOMAIN, 'approval-draft', id] as const,
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

/** 409 unless the statement is ISSUED; 422 on validation, total mismatch or a difference over the tolerance. */
export async function postSettlementDocument(
  id: number,
  payload: SettlementDocumentPayload,
): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.post<DetailResponse>(`${ENDPOINT}/${id}/document`, payload)
  return unwrap(data)
}

/** DOCUMENT_RECEIVED back to ISSUED; 409 otherwise. */
export async function deleteSettlementDocument(id: number): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.delete<DetailResponse>(`${ENDPOINT}/${id}/document`)
  return unwrap(data)
}

/** Supplier state and defaults of the approval dialog; 409 unless DOCUMENT_RECEIVED. */
export async function getApprovalDraft(id: number): Promise<ApprovalDraft> {
  const { data } = await apiClient.get<ApiResponse<ApprovalDraft>>(`${ENDPOINT}/${id}/approval-draft`)
  return data.data
}

/** Links an existing supplier registry (`registry_id`) or creates one from the referent (`null`). */
export async function postSettlementSupplier(id: number, payload: SettlementSupplierPayload): Promise<void> {
  await apiClient.post(`${ENDPOINT}/${id}/supplier`, payload)
}

/** Creates the passive invoice and moves the statement to APPROVED. */
export async function approveSettlement(
  id: number,
  payload: SettlementApprovePayload,
): Promise<SettlementDetailWithPermissions> {
  const { data } = await apiClient.post<DetailResponse>(`${ENDPOINT}/${id}/approve`, payload)
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
