import { BooleanBadgeCell, CodeBadgeCell, DateCell } from '@/features/table/rich-cells'
import { UserStackCell } from '@/features/table/user-cell'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { WorkOrderCompletionCell } from '@/features/work-orders/work-order-completion-bar'

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
 * and `start_date` (spec 0096) are date-only columns; `created_at`/
 * `updated_at` reuse the shared datetime renderer. `supervisors`
 * ("Responsabili", spec 0096) is a to-many of `{id,name,avatar_url}` and
 * reuses the SAME `UserStackCell` the Offerta's and Opportunita's own
 * `managers` columns render with — no second avatar-stack cell.
 * `title`/`contract_number`/`quote` stay on the AG Grid default text cell.
 * `completion_percentage` (spec 0149) renders the same toned bar as the
 * detail header.
 */

export const workOrderColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  is_force_closed: (params) => <BooleanBadgeCell {...params} />,
  callback_date: (params) => <DateCell {...params} />,
  start_date: (params) => <DateCell {...params} />,
  supervisors: (params) => <UserStackCell {...params} />,
  completion_percentage: (params) => <WorkOrderCompletionCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
