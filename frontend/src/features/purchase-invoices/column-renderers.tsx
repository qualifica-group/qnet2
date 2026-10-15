import { MoneyCell, PaymentStatusCell } from '@/features/invoices/invoice-cells'
import { PurchaseDocumentTypeCell } from '@/features/purchase-invoice-installments/purchase-installment-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { CodeBadgeCell, DateCell } from '@/features/table/rich-cells'

/** Custom cell renderers keyed by the backend column `id` (spec 0250 D-10); the payment status reuses the active-invoice semaphore. */
export const purchaseInvoiceColumnRenderers: TableRendererMap = {
  document_type: (params) => <PurchaseDocumentTypeCell {...params} />,
  document_number: (params) => <CodeBadgeCell {...params} />,
  document_date: (params) => <DateCell {...params} />,
  payment_status: (params) => <PaymentStatusCell {...params} />,
  total_amount: (params) => <MoneyCell {...params} />,
  payable_amount: (params) => <MoneyCell {...params} />,
  paid_amount: (params) => <MoneyCell {...params} />,
  residual_amount: (params) => <MoneyCell {...params} />,
}
