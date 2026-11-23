/**
 * Document checklist categories types (spec 0410 frozen `data_contract`). A
 * category is synchronized asynchronously to ArchivioSmart like a checklist:
 * `sync_status` tracks it and the sync logs share the checklist log shape.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { DocumentChecklistSyncStatus } from '@/features/document-checklists/types'

/** Category as returned by show/store/update/sync (envelope `data`). */
export interface DocumentChecklistCategory {
  id: number
  name: string
  description: string | null
  sort_order: number
  /** Non-deleted checklists that use the category. */
  checklists_count: number
  sync_status: DocumentChecklistSyncStatus
  sync_error: string | null
  synced_at: string | null
  created_at: string
  updated_at: string
}

/** A category carrying the actor's authorization metadata (envelope `permissions`). */
export interface DocumentChecklistCategoryWithPermissions extends DocumentChecklistCategory {
  permissions: ResourcePermissions
}

/** Body of POST /document-checklist-categories and PUT .../{id} (full replacement). */
export interface DocumentChecklistCategoryPayload {
  name: string
  description: string | null
  sort_order: number
}
