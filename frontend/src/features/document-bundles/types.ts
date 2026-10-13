/**
 * Document Bundles types (spec 0175 frozen `data_contract`). FE-03 (wave 2)
 * adds the CRUD write payloads and the form mode below the shared shape
 * FE-01 seeded. Files are managed through the generic `DocumentsSection`
 * (alias `document_bundle`, collection `documents`), not through this
 * feature's own endpoints.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Single document bundle as returned by GET/POST/PUT/PATCH
 * `/api/document-bundles[/{id}]` (envelope `data`).
 */
export interface DocumentBundle {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  description: string | null
  is_active: boolean
  files_count: number
  created_at: string
  updated_at: string
}

/**
 * A `DocumentBundle` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /document-bundles/{id}` (`show`).
 * Seeds the detail's `ResourcePermissionsProvider` without a second request.
 */
export interface DocumentBundleWithPermissions extends DocumentBundle {
  permissions: ResourcePermissions
}

/** Payload for POST /document-bundles (create). */
export interface CreateDocumentBundlePayload {
  name: string
  description?: string | null
  is_active?: boolean
}

/** Payload for PUT|PATCH /document-bundles/{id} (partial update). */
export type UpdateDocumentBundlePayload = Partial<CreateDocumentBundlePayload>

/**
 * Discriminated form mode of `useDocumentBundleForm`: `create` backs the
 * create form, `edit` the detail's in-place rows.
 */
export type DocumentBundleFormMode =
  | { type: 'create' }
  | { type: 'edit'; documentBundle: DocumentBundleWithPermissions }
