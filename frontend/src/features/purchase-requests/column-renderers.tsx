import { RelationCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { UserCell } from '@/features/table/user-cell'
import {
  LineStatusCell,
  LineStatusCountsCell,
  PriorityCell,
  RequestStatusCell,
  WorkOrderCell,
} from '@/features/purchase-requests/purchase-request-cells'

/** Custom cell renderers of the RDA grid, keyed by the backend column `id` (spec 0208 `data_contract`). */
export const purchaseRequestColumnRenderers: TableRendererMap = {
  priority: (params) => <PriorityCell {...params} />,
  requester: (params) => <UserCell {...params} />,
  function_manager: (params) => <UserCell {...params} />,
  created_by: (params) => <UserCell {...params} />,
  customer: (params) => <RelationCell {...params} />,
  supplier: (params) => <RelationCell {...params} />,
  work_order: (params) => <WorkOrderCell {...params} />,
  company: (params) => <RelationCell {...params} />,
  company_site: (params) => <RelationCell {...params} />,
  operational_site: (params) => <RelationCell {...params} />,
  business_function: (params) => <RelationCell {...params} />,
  status: (params) => <RequestStatusCell {...params} />,
  line_status_counts: (params) => <LineStatusCountsCell {...params} />,
}

/** Custom cell renderers of the "RDA lines" grid. */
export const purchaseRequestLineColumnRenderers: TableRendererMap = {
  priority: (params) => <PriorityCell {...params} />,
  requester: (params) => <UserCell {...params} />,
  function_manager: (params) => <UserCell {...params} />,
  approved_by: (params) => <UserCell {...params} />,
  unit_of_measure: (params) => <RelationCell {...params} />,
  status: (params) => <LineStatusCell {...params} />,
}
