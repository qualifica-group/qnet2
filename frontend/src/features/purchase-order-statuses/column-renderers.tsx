import { ColorSwatchCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers of the ODA status grid, keyed by the backend column
 * `id`: `color` renders a swatch dot + token name; `name`, `icon`,
 * `system_key` and `sort_order` fall back to the default cells.
 */
export const purchaseOrderStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
}
