import { MoneyCell } from '@/features/invoices/invoice-cells'
import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { PeriodCell } from './period-cell'

/** Custom cell renderers of the payroll costs grid, keyed by the backend column `id` (spec 0430 data_contract). */
export const payrollCostColumnRenderers: TableRendererMap = {
  period: (params) => <PeriodCell {...params} />,
  salary_cost: (params) => <MoneyCell {...params} />,
  contribution_cost: (params) => <MoneyCell {...params} />,
  insurance_cost: (params) => <MoneyCell {...params} />,
  total_cost: (params) => <MoneyCell {...params} />,
  allocated_cost: (params) => <MoneyCell {...params} />,
  unallocated_cost: (params) => <MoneyCell {...params} />,
  hired_on: (params) => <DateCell {...params} />,
  terminated_on: (params) => <DateCell {...params} />,
}
