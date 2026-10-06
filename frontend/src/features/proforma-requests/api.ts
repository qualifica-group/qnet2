import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  ProformaNotePayload,
  ProformaRequest,
  ProformaRequestWithPermissions,
  ProformaSummary,
} from '@/features/proforma-requests/types'

/** Table/module domain key (also the notable slug of `config/notes.php`). */
export const PROFORMA_REQUESTS_DOMAIN = 'proforma-requests'

/** Query key of a single proforma request's detail (fresh-on-open pattern). */
export function proformaRequestDetailQueryKey(id: number) {
  return [PROFORMA_REQUESTS_DOMAIN, 'detail', id] as const
}

/** Query key of a work order's proforma summary (the "€" modal). */
export function proformaSummaryQueryKey(workOrderId: number) {
  return [PROFORMA_REQUESTS_DOMAIN, 'summary', workOrderId] as const
}

/** Fetches one proforma request together with the actor's authorization metadata. */
export async function fetchProformaRequest(id: number): Promise<ProformaRequestWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<ProformaRequest, ResourcePermissions>>(
    `/proforma-requests/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Updates the note (PATCH): the only editable field. */
export async function updateProformaRequest(
  id: number,
  payload: ProformaNotePayload,
): Promise<ProformaRequest> {
  const { data } = await apiClient.patch<ApiResponse<ProformaRequest>>(`/proforma-requests/${id}`, payload)
  return data.data
}

/** Deletes a proforma request (204, no body). */
export async function deleteProformaRequest(id: number): Promise<void> {
  await apiClient.delete(`/proforma-requests/${id}`)
}

/** Reads the work order's proforma state, last request date and offer payment method. */
export async function fetchProformaSummary(workOrderId: number): Promise<ProformaSummary> {
  const { data } = await apiClient.get<ApiResponse<ProformaSummary>>(
    `/work-orders/${workOrderId}/proforma-requests/summary`,
  )
  return data.data
}

/** Generates the requests for a work order (one per consultancy / per distinct institution supplier). */
export async function createProformaRequests(
  workOrderId: number,
  payload: ProformaNotePayload,
): Promise<ProformaRequest[]> {
  const { data } = await apiClient.post<ApiResponse<ProformaRequest[]>>(
    `/work-orders/${workOrderId}/proforma-requests`,
    payload,
  )
  return data.data
}
