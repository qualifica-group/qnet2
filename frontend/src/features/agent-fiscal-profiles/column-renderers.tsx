import { BooleanBadgeCell, DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers of the profiles grid, keyed by the backend column `id` (spec 0212 `data_contract`). */
export const agentFiscalProfileColumnRenderers: TableRendererMap = {
  withholding_enabled: (params) => <BooleanBadgeCell {...params} />,
  enasarco_enabled: (params) => <BooleanBadgeCell {...params} />,
  valid_from: (params) => <DateCell {...params} />,
  valid_until: (params) => <DateCell {...params} />,
  updated_at: (params) => <DateCell {...params} />,
}
