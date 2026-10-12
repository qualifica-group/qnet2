import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers of the cost types grid, keyed by the backend column `id`. */
export const costTypeColumnRenderers: TableRendererMap = {
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
