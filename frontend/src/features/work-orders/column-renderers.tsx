import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell, CodeBadgeCell, DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0093
 * `data_contract`). `type`/`status` declare `enumKey` (`work_order_type`/
 * `work_order_status`, confirmed by backend): the generic `DataTable` already
 * renders those via the agnostic `BadgeCell` fallback (`resolveCellRenderer`),
 * localizing through `enums.work_order_type.*`/`enums.work_order_status.*`
 * (`it-enums.ts`/`en-enums.ts`), so they need no entry here. `is_force_closed`
 * is a plain boolean `badge` column with NO `enumKey`/`badges` (backend
 * confirmed): the generic fallback would render an empty cell, so it gets its
 * own `BooleanBadgeCell` (mirrors `contract-statuses`/`payment-methods`
 * `is_active`, shared `common.yes`/`common.no`). `code` renders as a compact
 * monospace badge (mirrors `unitOfMeasureColumnRenderers`); `callback_date`
 * is a date-only column; `created_at`/`updated_at` reuse the shared datetime
 * renderer. `title`/`contract_number`/`quote` stay on the AG Grid default
 * text cell.
 */
export const workOrderColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  is_force_closed: (params) => <BooleanBadgeCell {...params} />,
  callback_date: (params) => <DateCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
