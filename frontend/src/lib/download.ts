/**
 * Shared blob-download helpers, used by any feature that streams a file
 * response (imports, exports, …). Extracted from `features/imports/api.ts`
 * (spec 0012) so the second consumer (spec 0014, exports) does not duplicate
 * it (engineering.md §1.3 DRY).
 */
/** Matches a `filename="..."` (or unquoted) token in a `Content-Disposition` header. */
const CONTENT_DISPOSITION_FILENAME_RE = /filename="?([^";]+)"?/

/** Extracts the filename from a `Content-Disposition` header, if present. */
export function filenameFromContentDisposition(header: unknown): string | null {
  const match = typeof header === 'string' ? CONTENT_DISPOSITION_FILENAME_RE.exec(header) : null
  return match ? match[1] : null
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
