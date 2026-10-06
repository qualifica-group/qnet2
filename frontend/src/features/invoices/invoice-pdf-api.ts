import { apiClient } from '@/api/client'
import { filenameFromContentDisposition, normalizeBlobError, saveBlob } from '@/lib/download'

/**
 * Generates and downloads the document PDF (`GET /invoices/{id}/pdf`, spec 0195):
 * a binary response, not the `{success,message,data}` envelope. `layoutId`
 * optionally picks a layout of the invoices module; omitted = the active default.
 */
export async function downloadInvoicePdf(id: number, layoutId?: number): Promise<void> {
  try {
    const response = await apiClient.get<Blob>(`/invoices/${id}/pdf`, {
      params: layoutId ? { layout_id: layoutId } : undefined,
      responseType: 'blob',
    })
    saveBlob(response.data, filenameFromContentDisposition(response.headers['content-disposition']) ?? `invoice_${id}.pdf`)
  } catch (error) {
    throw await normalizeBlobError(error)
  }
}
