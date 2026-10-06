import { CodeBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import {
  InvoiceTagCell,
  InvoiceTypeCell,
  MoneyCell,
  PaymentStatusCell,
} from '@/features/invoices/invoice-cells'

/** Custom cell renderers keyed by the backend column `id` (spec 0194 `data_contract`). */
export const invoiceColumnRenderers: TableRendererMap = {
  number_label: (params) => <CodeBadgeCell {...params} />,
  work_order_code: (params) => <CodeBadgeCell {...params} />,
  quote_code: (params) => <CodeBadgeCell {...params} />,
  type: (params) => <InvoiceTypeCell {...params} />,
  tag: (params) => <InvoiceTagCell {...params} />,
  payment_status: (params) => <PaymentStatusCell {...params} />,
  net_amount: (params) => <MoneyCell {...params} />,
  vat_amount: (params) => <MoneyCell {...params} />,
  total_amount: (params) => <MoneyCell {...params} />,
  collected_amount: (params) => <MoneyCell {...params} />,
  residual_amount: (params) => <MoneyCell {...params} />,
  deviation: (params) => <MoneyCell {...params} />,
}
