import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { ChecklistSyncStatusCell } from '@/features/document-checklists/checklist-sync-status-cell'

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/`description`/
 * `sort_order`/`checklists_count` use the AG Grid defaults; `sync_status`
 * reads the hidden `sync_error` column of the same row for its tooltip.
 */
export const documentChecklistCategoryColumnRenderers: TableRendererMap = {
  sync_status: (params) => <ChecklistSyncStatusCell {...params} />,
  synced_at: (params) => <DateTimeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
