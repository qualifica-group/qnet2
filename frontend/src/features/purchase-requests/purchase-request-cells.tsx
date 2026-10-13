import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { usePurchaseRequestLineStatusCatalog } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { OdaLink } from '@/features/purchase-requests/oda-link'
import { LINE_STATUSES, type LineStatus, type LineStatusCounts, type PurchaseOrderRef } from '@/features/purchase-requests/types'

function isLineStatus(value: unknown): value is LineStatus {
  return typeof value === 'string' && (LINE_STATUSES as readonly string[]).includes(value)
}

/** Status of a line as the same badge the form shows. */
export function LineStatusCell({ value }: ICellRendererParams) {
  return isLineStatus(value) ? (
    <div className={CELL_WRAPPER}>
      <LineStatusBadge status={value} />
    </div>
  ) : (
    <EmptyCell />
  )
}

/** Per-status line counts of an RDA as compact "name n" chips in the catalog order, zero counts left out. */
export function LineStatusCountsCell({ value }: ICellRendererParams) {
  const { statuses } = usePurchaseRequestLineStatusCatalog()
  const counts = (value ?? {}) as LineStatusCounts
  const present = statuses.filter((status) => (counts[status.systemKey as LineStatus] ?? 0) > 0)
  if (present.length === 0) {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full flex-wrap items-center gap-1">
      {present.map((status) => (
        <Badge key={status.systemKey} variant="outline" className="text-[11px]">
          {status.name} {counts[status.systemKey as LineStatus]}
        </Badge>
      ))}
    </div>
  )
}

/** Request status (open | closed) as a translated badge. */
export function RequestStatusCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (value !== 'open' && value !== 'closed') {
    return <EmptyCell />
  }
  return (
    <div className={CELL_WRAPPER}>
      <Badge variant={value === 'open' ? 'secondary' : 'outline'} className="text-[11px]">
        {t(`purchaseRequests.requestStatuses.${value}`)}
      </Badge>
    </div>
  )
}

/** Work order `{id, code, title}` as its code, the title on hover. */
export function WorkOrderCell({ value }: ICellRendererParams) {
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

interface SupplierRef {
  name?: unknown
}

/** Distinct suppliers of an RDA's lines (spec 0209 D-6): names joined by comma, truncated with the full list as tooltip. */
export function SuppliersCell({ value }: ICellRendererParams) {
  const names = (Array.isArray(value) ? (value as SupplierRef[]) : [])
    .map((supplier) => supplier.name)
    .filter((name): name is string => typeof name === 'string' && name !== '')
  if (names.length === 0) {
    return <EmptyCell align="left" />
  }
  const joined = names.join(', ')
  return (
    <div className="flex h-full items-center overflow-hidden px-2">
      <span className="truncate" title={joined}>
        {joined}
      </span>
    </div>
  )
}

/** ODA column of the lines grid: link to the order when the row carries `purchase_order`, else the reference text. */
export function OdaCell({ value, data }: ICellRendererParams) {
  const order = (data as { purchase_order?: PurchaseOrderRef | null } | undefined)?.purchase_order ?? null
  const reference = typeof value === 'string' && value !== '' ? value : null
  if (!order && !reference) {
    return <EmptyCell align="left" />
  }
  return (
    <span className="truncate">
      <OdaLink purchaseOrder={order} odaReference={reference} />
    </span>
  )
}
