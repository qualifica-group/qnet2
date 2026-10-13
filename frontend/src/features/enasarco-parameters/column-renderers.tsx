import { MoneyCell } from '@/features/invoices/invoice-cells'
import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers of the parameters grid, keyed by the backend column `id` (spec 0213 `data_contract`). */
export const enasarcoParameterColumnRenderers: TableRendererMap = {
  single_firm_ceiling: (params) => <MoneyCell {...params} />,
  multi_firm_ceiling: (params) => <MoneyCell {...params} />,
  updated_at: (params) => <DateCell {...params} />,
}
