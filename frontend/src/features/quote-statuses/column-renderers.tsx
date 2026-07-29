import { DateTimeCell } from '@/features/table/cell-renderers'
import { ColorSwatchCell, GroupCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the shared
 * cross-module cell library so the swatch/group cells match other status
 * configurators. `name`/`sort_order` fall back to the AG Grid default
 * cells; `color` renders a swatch dot + localized token name; `group` renders
 * the fixed 3-value enum as a colored dot + label; `created_at` reuses the
 * shared datetime renderer.
 */
export const quoteStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
  group: (params) => <GroupCell {...params} labelPrefix="quoteStatuses.form.group" />,
  created_at: (params) => <DateTimeCell {...params} />,
}
