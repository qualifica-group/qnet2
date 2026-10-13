import { ColorSwatchCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers keyed by the backend column `id`; the other columns use the default cells. */
export const purchaseRequestLineStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
}
