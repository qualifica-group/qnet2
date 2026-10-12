import type { ICellRendererParams } from 'ag-grid-community'
import { CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import { PURCHASE_ORDER_STATUSES, type PurchaseOrderStatus } from '@/features/purchase-orders/types'

function isStatus(value: unknown): value is PurchaseOrderStatus {
  return typeof value === 'string' && (PURCHASE_ORDER_STATUSES as readonly string[]).includes(value)
}

/** Order status as the same badge the form shows. */
export function PurchaseOrderStatusCell({ value }: ICellRendererParams) {
  return isStatus(value) ? (
    <div className={CELL_WRAPPER}>
      <PurchaseOrderStatusBadge status={value} />
    </div>
  ) : (
    <EmptyCell />
  )
}

/** Work order or quote-like ref `{id, code, title}` as its code, the title on hover. */
export function WorkOrderCodeCell({ value }: ICellRendererParams) {
  const workOrder = value as { code?: string; title?: string | null } | null | undefined
  if (!workOrder?.code) {
    return <EmptyCell align="left" />
  }
  return (
    <span className="truncate" title={workOrder.title ?? workOrder.code}>
      {workOrder.code}
    </span>
  )
}
