import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers of the runs grid, keyed by the backend column `id` (spec 0213 `data_contract`). */
export const commissionSettlementRunColumnRenderers: TableRendererMap = {
  created_at: (params) => <DateCell {...params} />,
  period_from: (params) => <DateCell {...params} />,
  period_to: (params) => <DateCell {...params} />,
}
