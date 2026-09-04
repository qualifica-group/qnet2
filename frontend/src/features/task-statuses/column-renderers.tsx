import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell, ColorSwatchCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library so this grid matches the other
 * configurators (`contract-statuses`, `quote-statuses`). `name`/
 * `description`/`sort_order`/`icon`/`completion_percentage` fall back to the AG Grid
 * default cells; `color` renders a swatch dot + localized token name;
 * `is_active` renders a colored yes/no badge; `created_at` reuses the shared
 * datetime renderer. The catalogue exposes no `updated_at` column.
 */
export const taskStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
