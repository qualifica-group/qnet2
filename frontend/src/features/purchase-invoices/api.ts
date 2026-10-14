import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  InvoiceableOrder,
  PurchaseInstallmentPreviewPayload,
  PurchaseInstallmentPreviewRow,
  PurchaseInvoice,
  PurchaseInvoiceDetailsPayload,
  PurchaseInvoiceDraft,
  PurchaseInvoiceWritePayload,
} from '@/features/purchase-invoices/types'

/** Table/module domain key (SSRM domain, permission prefix and query-key root). */
export const PURCHASE_INVOICES_DOMAIN = 'purchase-invoices'

const BASE_PATH = '/purchase-invoices'

/** Centralized query keys; every passive invoice query hangs off `PURCHASE_INVOICES_DOMAIN`. */
export const purchaseInvoiceKeys = {
  all: [PURCHASE_INVOICES_DOMAIN] as const,
  detail: (id: number) => [PURCHASE_INVOICES_DOMAIN, 'detail', id] as const,
  draft: (orderIds: readonly number[]) => [PURCHASE_INVOICES_DOMAIN, 'draft', ...orderIds] as const,
  invoiceableOrders: (supplierId: number, companyId: number) =>
    [PURCHASE_INVOICES_DOMAIN, 'invoiceable-orders', supplierId, companyId] as const,
  preview: (payload: PurchaseInstallmentPreviewPayload) => [PURCHASE_INVOICES_DOMAIN, 'preview', payload] as const,
}

type DocumentEnvelope = ApiResponseWithPermissions<PurchaseInvoice, ResourcePermissions>

/** Reads one document (lines + installments) with the actor's permissions. */
export async function fetchPurchaseInvoice(id: number): Promise<PurchaseInvoice> {
  const { data } = await apiClient.get<DocumentEnvelope>(`${BASE_PATH}/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a document (201); lines carry cost type and work order. */
export async function createPurchaseInvoice(payload: PurchaseInvoiceWritePayload): Promise<PurchaseInvoice> {
  const { data } = await apiClient.post<DocumentEnvelope>(BASE_PATH, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Full update (PUT): 409 on a registered invoice; rows replaced, installments recomputed or rebalanced. */
export async function updatePurchaseInvoice(
  id: number,
  payload: PurchaseInvoiceWritePayload,
): Promise<PurchaseInvoice> {
  const { data } = await apiClient.put<DocumentEnvelope>(`${BASE_PATH}/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Supplier number/notes (PATCH): a filled number turns a proforma into an invoice. */
export async function updatePurchaseInvoiceDetails(
  id: number,
  payload: PurchaseInvoiceDetailsPayload,
): Promise<PurchaseInvoice> {
  const { data } = await apiClient.patch<DocumentEnvelope>(`${BASE_PATH}/${id}/details`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a document (204). 409 for an invoice or one with payments. */
export async function deletePurchaseInvoice(id: number): Promise<void> {
  await apiClient.delete(`${BASE_PATH}/${id}`)
}

/**
 * Prefilled draft from one or more purchase orders of the same supplier and company.
 * Quantities are the billable remainder, the same for proforma and invoice (spec 0254 D-2).
 */
export async function fetchPurchaseInvoiceDraft(orderIds: readonly number[]): Promise<PurchaseInvoiceDraft> {
  const { data } = await apiClient.get<ApiResponse<PurchaseInvoiceDraft>>(`${BASE_PATH}/draft`, {
    params: { purchase_order_ids: orderIds },
  })
  return data.data
}

/** Orders with something still billable, for a supplier and company. */
export async function fetchInvoiceableOrders(supplierId: number, companyId: number): Promise<InvoiceableOrder[]> {
  const { data } = await apiClient.get<ApiResponse<InvoiceableOrder[]>>(`${BASE_PATH}/invoiceable-orders`, {
    params: { supplier_id: supplierId, company_id: companyId },
  })
  return data.data
}

/** Installment plan computed by the server planner for the editor preview. */
export async function previewPurchaseInvoiceInstallments(
  payload: PurchaseInstallmentPreviewPayload,
): Promise<PurchaseInstallmentPreviewRow[]> {
  const { data } = await apiClient.post<ApiResponse<PurchaseInstallmentPreviewRow[]>>(
    `${BASE_PATH}/installment-preview`,
    payload,
  )
  return data.data
}
