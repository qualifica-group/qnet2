import { DateTimeCell } from '@/features/table/cell-renderers'
import { ColorSwatchCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { BehaviorCell } from '@/features/quote-system-statuses/behavior-cell'

/** Custom cell renderers keyed by the backend column `id`; the rest fall back to the AG Grid defaults. */
export const quoteSystemStatusColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
  behavior: (params) => <BehaviorCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
