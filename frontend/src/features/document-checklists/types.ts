/**
 * Document checklists types (spec 0403 frozen `data_contract`). The list is
 * synchronized asynchronously to ArchivioSmart: `sync_status` tracks it.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Allowed upload file types, same set as the backend `DocumentChecklistFileType` enum. */
export const DOCUMENT_CHECKLIST_FILE_TYPES = [
  'pdf',
  'jpg',
  'jpeg',
  'png',
  'webp',
  'heic',
  'docx',
  'odt',
  'txt',
] as const

export type DocumentChecklistFileType = (typeof DOCUMENT_CHECKLIST_FILE_TYPES)[number]

export type DocumentChecklistSyncStatus = 'pending' | 'synced' | 'failed'
export type DocumentChecklistSyncOperation = 'upsert' | 'delete'
export type DocumentChecklistSyncOutcome = 'success' | 'failed' | 'retrying'

/** Checklist entry as returned by the backend; the array order is the display order. */
export interface DocumentChecklistItem {
  id: number
  name: string
  description: string | null
  section: string | null
  is_required: boolean
  allowed_file_types: DocumentChecklistFileType[]
  max_file_size_kb: number
  min_files: number
  max_files: number
  validation_prompt: string | null
  max_age_days: number | null
}

/** Entry in a create/update request: `id` is null for a new entry. */
export interface DocumentChecklistItemPayload extends Omit<DocumentChecklistItem, 'id'> {
  id: number | null
}

/** Checklist as returned by show/store/update/activation (envelope `data`). */
export interface DocumentChecklist {
  id: number
  name: string
  code: string
  description: string | null
  is_active: boolean
  /** True once synced successfully: the code is then read-only. */
  code_locked: boolean
  sync_status: DocumentChecklistSyncStatus
  sync_error: string | null
  synced_at: string | null
  items: DocumentChecklistItem[]
  created_at: string
  updated_at: string
}

/** A `DocumentChecklist` carrying the actor's authorization metadata (envelope `permissions`). */
export interface DocumentChecklistWithPermissions extends DocumentChecklist {
  permissions: ResourcePermissions
}

/** Body of POST /document-checklists and PUT /document-checklists/{id} (full replacement). */
export interface DocumentChecklistPayload {
  name: string
  code: string
  description: string | null
  is_active: boolean
  items: DocumentChecklistItemPayload[]
}

/** One outbound ArchivioSmart call (GET /document-checklists/{id}/sync-logs). */
export interface DocumentChecklistSyncLog {
  id: number
  operation: DocumentChecklistSyncOperation
  outcome: DocumentChecklistSyncOutcome
  /** Null on network error or when the connection is not configured. */
  http_status: number | null
  attempt: number
  message: string | null
  created_at: string
}

/** Saved connection: the token is never returned, only whether it is set. */
export interface ArchivioSmartSettings {
  base_url: string | null
  has_token: boolean
  updated_at: string | null
}

/** Body of PUT /archivio-smart/settings; an omitted token leaves the saved one unchanged. */
export interface UpdateArchivioSmartSettingsPayload {
  base_url: string
  token?: string
}

/** Outcome of POST /archivio-smart/settings/test. */
export interface ArchivioSmartConnectionTest {
  ok: boolean
  http_status: number | null
  message: string
}
