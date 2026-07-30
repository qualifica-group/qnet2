import axios from 'axios'
import { apiClient } from '@/api/client'
import { filenameFromContentDisposition, saveBlob } from '@/lib/download'

/** Fallback filename when the response carries no `Content-Disposition` header (should never happen server-side, spec 0070). */
function fallbackDocumentFilename(quoteCode: string): string {
  return `${quoteCode}.docx`
}

/**
 * A failed `responseType: 'blob'` request receives its JSON error envelope as
 * an opaque `Blob`, not the parsed `{success,message}` object axios hands a
 * normal JSON response — so `error.response?.data?.message` (how the rest of
 * the app reads a business 422's message, e.g. `DocumentLayoutsTable`'s
 * delete-guard handling) would silently read `undefined` here. Rewrites the
 * SAME axios error's `data` in place from the blob's text, so
 * `use-quote-document.ts` can read `error.response.data.message` exactly like
 * every other 422 handler in the app (AC-303's `no_layout_available`).
 */
async function normalizeBlobError(error: unknown): Promise<unknown> {
  if (!axios.isAxiosError(error) || !(error.response?.data instanceof Blob)) {
    return error
  }
  try {
    const text = await error.response.data.text()
    error.response.data = JSON.parse(text)
  } catch {
    // Not a JSON body (e.g. an HTML 500 page): leave the blob as-is.
  }
  return error
}

/**
 * Generates and downloads the quote's Word document
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
