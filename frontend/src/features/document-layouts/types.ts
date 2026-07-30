/**
 * Document layouts CRUD types (spec 0069). Cloned structurally from
 * `payment-methods` (spec 0068), the canonical CRUD reference: `code` is
 * unique and immutable after create, `module` is immutable after create too
 * (changing it would invalidate every variable token already used by the
 * layout, see spec 0069 `validation`). The `config` column's own contract
 * (blocks/zones/page) lives in `layout-config.ts`, not here — this file only
 * holds the resource envelope and metadata write payloads.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'

/**
 * Supported consumer modules for a layout (`App\Enums\DocumentLayoutModule`).
 * Only `quotes` exists today; the enum is deliberately extendable without a
 * frontend shape change (spec 0069 goal).
 */
export const DOCUMENT_LAYOUT_MODULES = ['quotes'] as const
export type DocumentLayoutModule = (typeof DOCUMENT_LAYOUT_MODULES)[number]

/** Metadata-only projection of an uploaded layout image (no binary/data URI). */
export interface DocumentLayoutImageMeta {
  attachment_id: number
  filename: string
  mime_type: string
  size: number
}

/**
 * Single document layout detail returned by GET/POST/PATCH
 * `/document-layouts` (envelope `data`). Matches `DocumentLayoutResource`.
 */
export interface DocumentLayoutDetail {
  id: number
  name: string
  /** Snake_case-shaped identifier, unique, immutable after create. */
  code: string
  description: string | null
  module: DocumentLayoutModule
  /** Localized display label for `module` (i18next, server-rendered). */
  module_label: string
  is_active: boolean
  is_default: boolean
  config: DocumentLayoutConfig
  images: DocumentLayoutImageMeta[]
  created_at: string
  updated_at: string
}

/**
 * A `DocumentLayoutDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /document-layouts/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface DocumentLayoutDetailWithPermissions extends DocumentLayoutDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /document-layouts (create). `config` is required by the
 * backend on create: the metadata-only form (wave 1) sends the empty default
 * config (`createEmptyDocumentLayoutConfig()`), the visual editor (wave 2)
 * sends the edited one.
 */
export interface CreateDocumentLayoutPayload {
  name: string
  code: string
  module: DocumentLayoutModule
  config: DocumentLayoutConfig
  description?: string | null
  is_active?: boolean
  is_default?: boolean
}

/**
 * Payload for PATCH /document-layouts/{id} (partial update). `code` and
 * `module` are PROHIBITED at the HTTP level (spec 0069 `validation`): they
 * are never keys of this type, so a caller cannot accidentally include them.
 */
export type UpdateDocumentLayoutPayload = Partial<
  Omit<CreateDocumentLayoutPayload, 'code' | 'module'>
>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `DocumentLayoutForm` component.
 */
export type DocumentLayoutFormMode =
  | { type: 'create' }
  | { type: 'edit'; documentLayout: DocumentLayoutDetailWithPermissions }
