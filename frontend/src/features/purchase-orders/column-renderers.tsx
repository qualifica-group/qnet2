import { BooleanBadgeCell, CodeBadgeCell, CurrencyCell, DateCell, RefNamesCell, RelationCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { UserCell } from '@/features/table/user-cell'
import { PurchaseOrderStatusCell, WorkOrderCodeCell } from '@/features/purchase-orders/purchase-order-cells'

/** Custom cell renderers of the ODA grid, keyed by the backend column `id` (spec 0210 `data_contract`). */
export const purchaseOrderColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  status: (params) => <PurchaseOrderStatusCell {...params} />,
  order_date: (params) => <DateCell {...params} />,
  supplier: (params) => <RelationCell {...params} />,
  company: (params) => <RelationCell {...params} />,
  company_site: (params) => <RelationCell {...params} />,
  operational_site: (params) => <RelationCell {...params} />,
  payment_method: (params) => <RelationCell {...params} />,
  customer: (params) => <RelationCell {...params} />,
  quote: (params) => <RelationCell {...params} />,
  opportunity: (params) => <RelationCell {...params} />,
  work_order: (params) => <WorkOrderCodeCell {...params} />,
  sectors: (params) => <RefNamesCell {...params} />,
  is_internal_user: (params) => <BooleanBadgeCell {...params} />,
  taxable_total: (params) => <CurrencyCell {...params} />,
  vat_total: (params) => <CurrencyCell {...params} />,
  grand_total: (params) => <CurrencyCell {...params} />,
  created_by: (params) => <UserCell {...params} />,
}
