import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  Invoice,
  InvoiceCollectionPayload,
  InvoiceDetailsPayload,
  InvoiceDraft,
  InvoiceWithPermissions,
  InvoiceWritePayload,
  InstallmentPreviewPayload,
  InstallmentPreviewRow,
  MonthlySummary,
  MonthlySummaryParams,
} from '@/features/invoices/types'

/** Table/module domain key (SSRM domain, permission prefix and query-key root). */
export const INVOICES_DOMAIN = 'invoices'

/** Centralized query keys; every invoice query hangs off `INVOICES_DOMAIN`. */
export const invoiceKeys = {
  all: [INVOICES_DOMAIN] as const,
  detail: (id: number) => [INVOICES_DOMAIN, 'detail', id] as const,
  draft: (proformaRequestId: number) => [INVOICES_DOMAIN, 'draft', proformaRequestId] as const,
  preview: (payload: InstallmentPreviewPayload) => [INVOICES_DOMAIN, 'preview', payload] as const,
  monthlySummary: (params: MonthlySummaryParams) =>
    [INVOICES_DOMAIN, 'monthly-summary', params.year, params.type] as const,
}

/** Reads the prefilled draft of the issue modal for a proforma request. */
export async function getInvoiceDraft(proformaRequestId: number): Promise<InvoiceDraft> {
  const { data } = await apiClient.get<ApiResponse<InvoiceDraft>>(
    `/proforma-requests/${proformaRequestId}/invoice-draft`,
  )
  return data.data
}

/** Issues the document for a proforma request (201). 409 when already invoiced. */
export async function createInvoice(
  proformaRequestId: number,
  payload: InvoiceWritePayload,
): Promise<Invoice> {
  const { data } = await apiClient.post<ApiResponse<Invoice>>(
    `/proforma-requests/${proformaRequestId}/invoice`,
    payload,
  )
  return data.data
}

/** Computes the installment schedule for the modal preview (server-side calculator). */
export async function previewInstallments(
  payload: InstallmentPreviewPayload,
): Promise<InstallmentPreviewRow[]> {
  const { data } = await apiClient.post<ApiResponse<InstallmentPreviewRow[]>>(
    '/invoices/installment-preview',
    payload,
  )
  return data.data
}

/** Fetches one document (lines + installments) with the actor's permissions. */
export async function getInvoice(id: number): Promise<InvoiceWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<Invoice, ResourcePermissions>>(
    `/invoices/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Full update (PUT): lines replaced, installments recomputed. 409 with collections. */
export async function updateInvoice(
  id: number,
  payload: InvoiceWritePayload,
): Promise<InvoiceWithPermissions> {
  const { data } = await apiClient.put<ApiResponseWithPermissions<Invoice, ResourcePermissions>>(
    `/invoices/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Updates external number/date, tag, deviation and internal note (always allowed). */
export async function updateInvoiceDetails(
  id: number,
  payload: InvoiceDetailsPayload,
): Promise<InvoiceWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<Invoice, ResourcePermissions>>(
    `/invoices/${id}/details`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a document (204). 409 when an installment has collections. */
export async function deleteInvoice(id: number): Promise<void> {
  await apiClient.delete(`/invoices/${id}`)
}

/** Records a collection on an installment; returns the whole document. */
export async function recordCollection(
  installmentId: number,
  body: InvoiceCollectionPayload,
): Promise<Invoice> {
  const { data } = await apiClient.put<ApiResponse<Invoice>>(
    `/invoice-installments/${installmentId}/collection`,
    body,
  )
  return data.data
}

/** Clears the collection of an installment; returns the whole document. */
export async function clearCollection(installmentId: number): Promise<Invoice> {
  const { data } = await apiClient.delete<ApiResponse<Invoice>>(
    `/invoice-installments/${installmentId}/collection`,
  )
  return data.data
}

/** Per-month count/total strip data for the list. */
export async function getMonthlySummary(params: MonthlySummaryParams): Promise<MonthlySummary> {
  const { data } = await apiClient.get<ApiResponse<MonthlySummary>>('/invoices/monthly-summary', {
    params,
  })
  return data.data
}
