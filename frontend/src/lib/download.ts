/**
 * Shared blob-download helpers, used by any feature that streams a file
 * response (imports, exports, …). Extracted from `features/imports/api.ts`
 * (spec 0012) so the second consumer (spec 0014, exports) does not duplicate
 * it (engineering.md §1.3 DRY).
 */
import axios from 'axios'

/** Matches a `filename="..."` (or unquoted) token in a `Content-Disposition` header. */
const CONTENT_DISPOSITION_FILENAME_RE = /filename="?([^";]+)"?/

/** Extracts the filename from a `Content-Disposition` header, if present. */
export function filenameFromContentDisposition(header: unknown): string | null {
  const match = typeof header === 'string' ? CONTENT_DISPOSITION_FILENAME_RE.exec(header) : null
  return match ? match[1] : null
}

/**
 * A failed `responseType: 'blob'` request carries its JSON error envelope as an
 * opaque Blob, so `error.response.data.message` would read `undefined`. Rewrites
 * the SAME axios error's `data` in place from the blob's text, so callers read a
 * business 422 like any other. Non-axios errors and non-JSON bodies pass through.
 */
export async function normalizeBlobError(error: unknown): Promise<unknown> {
  if (!axios.isAxiosError(error) || !(error.response?.data instanceof Blob)) {
    return error
  }
  try {
    error.response.data = JSON.parse(await error.response.data.text())
  } catch {
    // Not a JSON body (e.g. an HTML 500 page): leave the blob as-is.
  }
  return error
}

/** Saves a blob response to disk via a transient, invisible anchor click. */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}
