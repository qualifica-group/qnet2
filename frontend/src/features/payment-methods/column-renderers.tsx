import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell, CodeBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library. `name`/`description`/`payment_days`/
 * `sort_order` fall back to the AG Grid default cells; `code` renders as a
 * compact monospace badge (mirrors other short record identifiers);
 * `is_active` renders a colored yes/no badge; `created_at`/`updated_at`
 * reuse the shared datetime renderer (mirrors `rewardStatusColumnRenderers`).
 */
export const paymentMethodColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
