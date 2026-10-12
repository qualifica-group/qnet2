import { MoneyCell } from '@/features/invoices/invoice-cells'
import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import {
  PurchaseDaysOverdueCell,
  PurchaseDocumentTypeCell,
  PurchaseOverdueCell,
  PurchaseStatusCell,
} from '@/features/purchase-invoice-installments/purchase-installment-cells'

/** Custom cell renderers keyed by the backend column `id` (spec 0211 `data_contract`). */
export const purchaseInstallmentColumnRenderers: TableRendererMap = {
  document_type: (params) => <PurchaseDocumentTypeCell {...params} />,
  document_date: (params) => <DateCell {...params} />,
  due_date: (params) => <DateCell {...params} />,
  paid_at: (params) => <DateCell {...params} />,
  status: (params) => <PurchaseStatusCell {...params} />,
  overdue: (params) => <PurchaseOverdueCell {...params} />,
  days_overdue: (params) => <PurchaseDaysOverdueCell {...params} />,
  amount: (params) => <MoneyCell {...params} />,
  paid_amount: (params) => <MoneyCell {...params} />,
  residual_amount: (params) => <MoneyCell {...params} />,
}
