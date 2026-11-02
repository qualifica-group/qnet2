/**
 * Generic polymorphic file-attachment types. Mirrors the frozen contract of
 * the Attachment API (`/api/attachments*`): the endpoints are generic across
 * modules, so this feature carries no per-module knowledge beyond the
 * `attachable_type` alias the caller passes in (e.g. `'opportunity'`, per
 * `config('attachments.attachable_types')` — NOT the plural table-registry
 * resource key used by the activity log).
 */

/** One stored file attachment, as returned by the Attachment API. */
export interface Attachment {
  id: number
  collection: string | null
  original_name: string
  mime_type: string
  extension: string | null
  size: number
  attachable_type: string
  attachable_id: number
  uploaded_by: number | null
  /** Authenticated, authorized download endpoint (forces "Save as"). */
  download_url: string
  /** Same authorization boundary as `download_url`, served inline for preview. */
  view_url: string
  created_at: string
}

/**
 * One owner's document list, plus the legacy QNet page where the documents of
 * a migrated record can still be looked up (spec 0343; null when not migrated).
 */
export interface AttachmentList {
  documents: Attachment[]
  legacyUrl: string | null
}

/** Default collection used by `<DocumentsSection>` when the caller omits one. */
export const DOCUMENTS_COLLECTION = 'documents'
