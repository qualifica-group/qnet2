import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { LINE_STATUSES, type LineStatus, type LineStatusCounts } from '@/features/purchase-requests/types'

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

/** Per-status line counts of an RDA as compact "label n" chips, zero counts left out. */
export function LineStatusCountsCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const counts = (value ?? {}) as LineStatusCounts
  const present = LINE_STATUSES.filter((status) => (counts[status] ?? 0) > 0)
  if (present.length === 0) {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full flex-wrap items-center gap-1">
      {present.map((status) => (
        <Badge key={status} variant="outline" className="text-[11px]">
          {t(`purchaseRequests.lineStatuses.${status}`)} {counts[status]}
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

/** Priority as a translated plain label. */
export function PriorityCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  return typeof value === 'string' && value !== '' ? (
    <span className="truncate">{t(`purchaseRequests.priorities.${value}`)}</span>
  ) : (
    <EmptyCell align="left" />
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
