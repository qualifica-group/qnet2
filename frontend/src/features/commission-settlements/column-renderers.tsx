import { MoneyCell } from '@/features/invoices/invoice-cells'
import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Custom cell renderers keyed by the backend column `id` (spec 0213 `data_contract`); the status badge uses the default cell. */
export const commissionSettlementColumnRenderers: TableRendererMap = {
  period_from: (params) => <DateCell {...params} />,
  period_to: (params) => <DateCell {...params} />,
  issued_at: (params) => <DateCell {...params} />,
  taxable_amount: (params) => <MoneyCell {...params} />,
  vat_amount: (params) => <MoneyCell {...params} />,
  withholding_amount: (params) => <MoneyCell {...params} />,
  enasarco_agent_amount: (params) => <MoneyCell {...params} />,
  enasarco_principal_amount: (params) => <MoneyCell {...params} />,
  net_payable: (params) => <MoneyCell {...params} />,
}
