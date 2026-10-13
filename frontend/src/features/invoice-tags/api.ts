import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateInvoiceTagPayload,
  InvoiceTagDetail,
  InvoiceTagDetailWithPermissions,
  UpdateInvoiceTagPayload,
} from '@/features/invoice-tags/types'

/** Query key of a single invoice tag's detail, shared by the detail screen and its in-place saves. */
export function invoiceTagDetailQueryKey(id: number) {
  return ['invoice-tags', 'detail', id] as const
}

/**
 * Fetches a single invoice tag detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchInvoiceTag(id: number): Promise<InvoiceTagDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<InvoiceTagDetail, ResourcePermissions>
  >(`/invoice-tags/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates an invoice tag. Returns the created resource from the envelope `data`. */
export async function createInvoiceTag(payload: CreateInvoiceTagPayload): Promise<InvoiceTagDetail> {
  const { data } = await apiClient.post<ApiResponse<InvoiceTagDetail>>('/invoice-tags', payload)
  return data.data
}

/**
 * Partially updates an invoice tag (PATCH). `sort_order` is
 * never a key of `UpdateInvoiceTagPayload` (spec 0247 `data_contract`): the backend
 * rejects their mere presence with 422. Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateInvoiceTag(
  id: number,
  payload: UpdateInvoiceTagPayload,
): Promise<InvoiceTagDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<InvoiceTagDetail, ResourcePermissions>
  >(`/invoice-tags/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/**
 * Deletes an invoice tag. The backend answers 409 when the row is still used by
 * an invoice (spec 0247 D-5); both branches are surfaced by the
 * caller (`InvoiceTagsTable.runDelete`), not here.
 */
export async function deleteInvoiceTag(id: number): Promise<void> {
  await apiClient.delete(`/invoice-tags/${id}`)
}
