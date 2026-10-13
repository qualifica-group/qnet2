import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { TableRow } from '@/features/table/types'
import { fetchPurchaseOrder, purchaseOrderKeys } from '@/features/purchase-orders/api'
import type { PurchaseOrderLine } from '@/features/purchase-orders/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

function LineRow({ line }: { line: PurchaseOrderLine }) {
  return (
    <tr className="border-b border-border last:border-b-0">
      <td className={NUM_CELL}>{line.position}</td>
      <td className={`${CELL} max-w-80`}>
        <div className="truncate" title={line.description}>{line.description}</div>
      </td>
      <td className={CELL}>
        <CostTypeBadge name={line.cost_type.name} color={line.cost_type.color} />
      </td>
      <td className={CELL}>{line.unit_of_measure?.symbol ?? line.unit_of_measure?.name ?? '—'}</td>
      <td className={NUM_CELL}>{Number(line.quantity)}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.unit_price))}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.taxable_amount))}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.vat_amount))}</td>
      <td className={NUM_CELL}>{formatQuoteAmount(Number(line.total_amount))}</td>
    </tr>
  )
}

/**
 * Master/detail row of the ODA list: lazily loads the order and shows its
 * lines. Height is pushed back to AG Grid through a ResizeObserver (same
 * mechanism as the RDA panel).
 */
export function PurchaseOrderDetailPanel({ data, node, api }: ICellRendererParams<TableRow>) {
  const { t } = useTranslation()
  const orderId = typeof data?.id === 'number' ? data.id : null
  const detail = useEntityDetail(
    purchaseOrderKeys.detail(orderId ?? 0),
    () => fetchPurchaseOrder(orderId as number),
    orderId !== null,
  )

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

  if (orderId === null) {
    return null
  }
  if (detail.isError) {
    return (
      <div className="flex flex-col items-start gap-2 p-3">
        <p className="text-sm text-destructive">{t('purchaseOrders.form.loadError')}</p>
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void detail.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!detail.data) {
    return <Skeleton className="m-3 h-20" />
  }

  return (
    <div ref={contentRef} className="p-3">
      <div className="overflow-x-auto rounded-lg border border-border bg-card">
        <table className="w-full min-w-max border-collapse">
          <caption className="sr-only">{t('purchaseOrders.sections.lines')}</caption>
          <thead className="border-b border-border bg-surface">
            <tr>
              <th scope="col" className={NUM_HEAD}>#</th>
              <th scope="col" className={HEAD}>{t('purchaseOrders.lines.description')}</th>
              <th scope="col" className={HEAD}>{t('purchaseOrders.lines.costType')}</th>
              <th scope="col" className={HEAD}>{t('purchaseOrders.lines.unit')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseOrders.lines.quantity')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseOrders.lines.unitPrice')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseOrders.lines.taxable')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseOrders.lines.vat')}</th>
              <th scope="col" className={NUM_HEAD}>{t('purchaseOrders.lines.total')}</th>
            </tr>
          </thead>
          <tbody>
            {detail.data.lines.map((line) => (
              <LineRow key={line.id} line={line} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
