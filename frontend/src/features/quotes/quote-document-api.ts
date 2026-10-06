import { apiClient } from '@/api/client'
import { filenameFromContentDisposition, normalizeBlobError, saveBlob } from '@/lib/download'

/** Fallback filename when the response carries no `Content-Disposition` header (should never happen server-side, spec 0070). */
function fallbackDocumentFilename(quoteCode: string): string {
  return `${quoteCode}.pdf`
}

/**
 * Generates and downloads the quote's PDF document
 * (`POST /quotes/{id}/document`, spec 0070). A binary streaming response, NOT
 * the standard `{success,message,data}` envelope — mirrors `downloadExport`.
 * No request body: the endpoint reads the quote's own `layout_id` (or the
 * module's active default, D-3) server-side.
 */
export async function generateQuoteDocument(quoteId: number, quoteCode: string): Promise<void> {
  try {
    const response = await apiClient.post<Blob>(`/quotes/${quoteId}/document`, undefined, {
      responseType: 'blob',
    })
    const filename =
      filenameFromContentDisposition(response.headers['content-disposition']) ??
      fallbackDocumentFilename(quoteCode)
    saveBlob(response.data, filename)
  } catch (error) {
    throw await normalizeBlobError(error)
  }
}
