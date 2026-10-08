import { DateTimeCell } from '@/features/table/cell-renderers'
import { CodeBadgeCell, RelationCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { UserCell } from '@/features/table/user-cell'
import { ProformaKindCell, ProformaStatusBadgeCell } from '@/features/proforma-requests/proforma-cells'

/** Custom cell renderers keyed by the backend column `id` (spec 0193 `data_contract`). */
export const proformaRequestColumnRenderers: TableRendererMap = {
  work_order_code: (params) => <CodeBadgeCell {...params} />,
  company: (params) => <RelationCell {...params} />,
  customer: (params) => <RelationCell {...params} />,
  supplier: (params) => <RelationCell {...params} />,
  payment_method: (params) => <RelationCell {...params} />,
  kind: (params) => <ProformaKindCell {...params} />,
  status: (params) => <ProformaStatusBadgeCell {...params} />,
  assigned_to: (params) => <UserCell {...params} />,
  assigned_by: (params) => <UserCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
