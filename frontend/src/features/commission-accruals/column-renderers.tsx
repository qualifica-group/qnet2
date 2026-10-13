import { MoneyCell } from '@/features/invoices/invoice-cells'
import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers keyed by the backend column `id` (spec 0212 `data_contract`); badges use the default cell. */
export const commissionAccrualColumnRenderers: TableRendererMap = {
  accrued_on: (params) => <DateCell {...params} />,
  settled_externally_on: (params) => <DateCell {...params} />,
  base_amount: (params) => <MoneyCell {...params} />,
  amount: (params) => <MoneyCell {...params} />,
}
