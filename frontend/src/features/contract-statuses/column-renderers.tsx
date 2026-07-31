import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell, ColorSwatchCell, GroupCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library so the swatch/group/boolean cells match
 * the other status configurators (`quote-statuses`, `document-layouts`).
 * `name`/`description`/`sort_order` fall back to the AG Grid default cells;
 * `color` renders a swatch dot + localized token name; `group` renders the
 * fixed 4-value enum as a colored dot + label; `is_active`/`is_default`
 * render a colored yes/no badge (`is_default` stays display-only in the
 * grid, BR-5: it is reassigned from the form, never inline-edited);
 * `created_at`/`updated_at` reuse the shared datetime renderer.
 */
export const contractStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
  group: (params) => <GroupCell {...params} labelPrefix="contractStatuses.form.group" />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  is_default: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
