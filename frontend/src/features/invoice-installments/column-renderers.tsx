import { MoneyCell } from '@/features/invoices/invoice-cells'
import { CodeBadgeCell, DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import {
  DaysOverdueCell,
  InstallmentStatusCell,
  OverdueCell,
} from '@/features/invoice-installments/installment-cells'

/** Custom cell renderers keyed by the backend column `id` (spec 0197 `data_contract`). */
export const installmentColumnRenderers: TableRendererMap = {
  invoice_number_label: (params) => <CodeBadgeCell {...params} />,
  invoice_document_date: (params) => <DateCell {...params} />,
  due_date: (params) => <DateCell {...params} />,
  collected_at: (params) => <DateCell {...params} />,
  status: (params) => <InstallmentStatusCell {...params} />,
  overdue: (params) => <OverdueCell {...params} />,
  days_overdue: (params) => <DaysOverdueCell {...params} />,
  amount: (params) => <MoneyCell {...params} />,
  collected_amount: (params) => <MoneyCell {...params} />,
  residual_amount: (params) => <MoneyCell {...params} />,
}
