import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { TableRow } from '@/features/table/types'
import { fetchPurchaseRequest, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { LineStatusDialog } from '@/features/purchase-requests/line-status-dialog'
import { lineToTarget, type LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import type { PurchaseRequestLine } from '@/features/purchase-requests/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'
const NO_TARGETS: readonly LineTarget[] = []

type PurchaseRequestDetailPanelProps = ICellRendererParams<TableRow> & {
  /** Refreshes the master grid after a status change (counts, auto-closure). */
  onChanged: () => void
}

function LineRow({ line, onChangeStatus }: { line: PurchaseRequestLine; onChangeStatus: () => void }) {
  const { t } = useTranslation()
  return (
    <tr className="border-b border-border last:border-b-0">
      <td className={NUM_CELL}>{line.position}</td>
      <td className={`${CELL} max-w-80`}>
        <div className="truncate" title={line.description}>{line.description}</div>
        {line.reason ? <div className="truncate text-muted-foreground" title={line.reason}>{line.reason}</div> : null}
      </td>
      <td className={CELL}>{line.unit_of_measure?.symbol ?? line.unit_of_measure?.name ?? '—'}</td>
      <td className={NUM_CELL}>{Number(line.quantity)}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.unit_price))}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.taxable_amount))}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.vat_amount))}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.total_amount))}</td>
      <td className={CELL}>
        <LineStatusBadge status={line.status} />
      </td>
      <td className={`${CELL} text-right`}>
        {line.abilities.transitions.length > 0 ? (
          <Button type="button" variant="secondary" size="xs" onClick={onChangeStatus}>
            <ListChecks aria-hidden="true" />
            {t('purchaseRequests.lines.changeStatus')}
          </Button>
        ) : null}
      </td>
    </tr>
  )
}

/**
 * Master/detail row of the RDA list: lazily loads the request and shows its
 * lines with status and the per-line status change. Height is pushed back to
 * AG Grid through a ResizeObserver (same mechanism as the invoices panel).
 */
export function PurchaseRequestDetailPanel({ data, node, api, onChanged }: PurchaseRequestDetailPanelProps) {
  const { t } = useTranslation()
  const requestId = typeof data?.id === 'number' ? data.id : null
  const detail = useEntityDetail(
    purchaseRequestKeys.detail(requestId ?? 0),
    () => fetchPurchaseRequest(requestId as number),
    requestId !== null,
  )
  const [targets, setTargets] = useState<readonly LineTarget[]>(NO_TARGETS)

  const contentRef = useRef<HTMLDivElement>(null)
  const isReady = detail.data !== undefined
  useEffect(() => {
    const element = contentRef.current
    if (!element || !node || !api || typeof ResizeObserver === 'undefined') {
      return
    }
    const observer = new ResizeObserver(() => {
      node.setRowHeight(element.offsetHeight)
      api.onRowHeightChanged()
    })
    observer.observe(element)
    return () => observer.disconnect()
  }, [node, api, isReady])

  if (requestId === null) {
    return null
  }
  if (detail.isError) {
    return (
      <div className="flex flex-col items-start gap-2 p-3">
        <p className="text-sm text-destructive">{t('purchaseRequests.form.loadError')}</p>
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void detail.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!detail.data) {
    return <Skeleton className="m-3 h-20" />
  }

  const request = detail.data
  const isClosed = request.status === 'closed'
  return (
    <div ref={contentRef} className="p-3">
      <div className="overflow-x-auto rounded-lg border border-border bg-card">
        <table className="w-full min-w-max border-collapse">
          <caption className="sr-only">{t('purchaseRequests.sections.lines')}</caption>
          <thead className="border-b border-border bg-surface">
            <tr>
              <th scope="col" className={NUM_HEAD}>#</th>
              <th scope="col" className={HEAD}>{t('purchaseRequests.lines.description')}</th>
              <th scope="col" className={HEAD}>{t('purchaseRequests.lines.unit')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.quantity')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.unitPrice')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.taxable')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.vat')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.total')}</th>
              <th scope="col" className={HEAD}>{t('purchaseRequests.lines.status')}</th>
              <th scope="col" className={HEAD}>
                <span className="sr-only">{t('purchaseRequests.lines.changeStatus')}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {request.lines.map((line) => (
              <LineRow
                key={line.id}
                line={isClosed ? { ...line, abilities: { ...line.abilities, transitions: [] } } : line}
                onChangeStatus={() =>
                  setTargets([lineToTarget(line, request, isClosed ? [] : line.abilities.transitions)])
                }
              />
            ))}
          </tbody>
        </table>
      </div>
      <LineStatusDialog
        key={targets.map((target) => target.id).join(',')}
        targets={targets}
        onOpenChange={(open) => (open ? undefined : setTargets(NO_TARGETS))}
        onChanged={() => {
          setTargets(NO_TARGETS)
          onChanged()
        }}
      />
    </div>
  )
}
