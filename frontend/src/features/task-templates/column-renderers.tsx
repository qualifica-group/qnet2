import { CountCell, DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library. `name`/`description` fall back to the AG
 * Grid default cells; `items_count` reuses the shared count cell,
 * `is_active`/`created_at`/`updated_at` reuse the same cells every other
 * configurator grid uses (mirrors `quoteWorkflowColumnRenderers`).
 */
export const taskTemplateColumnRenderers: TableRendererMap = {
  items_count: (params) => <CountCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
