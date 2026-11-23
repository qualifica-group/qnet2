import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { ChecklistSyncStatusCell } from '@/features/document-checklists/checklist-sync-status-cell'

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/`code`/
 * `items_count` use the AG Grid defaults; `sync_status` reads the hidden
 * `sync_error` column of the same row for its tooltip.
 */
export const documentChecklistColumnRenderers: TableRendererMap = {
  is_active: (params) => <BooleanBadgeCell {...params} />,
  sync_status: (params) => <ChecklistSyncStatusCell {...params} />,
  synced_at: (params) => <DateTimeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
