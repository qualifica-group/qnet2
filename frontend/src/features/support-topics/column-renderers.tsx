import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Cell renderers keyed by backend column `id`; the others use the AG Grid default cells. */
export const supportTopicColumnRenderers: TableRendererMap = {
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
