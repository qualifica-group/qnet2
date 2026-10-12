import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { formatDate } from '@/lib/formatting/date-display'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { orderSelectionState } from '@/features/purchase-orders/purchase-order-merge-selection'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import type { MergeCandidate } from '@/features/purchase-orders/types'

interface PurchaseOrderMergeCandidateProps {
  candidate: MergeCandidate
  selected: ReadonlySet<number>
  onToggleOrder: (candidate: MergeCandidate) => void
  onToggleLine: (lineId: number) => void
}

/** One candidate ODA: header checkbox selects every line, each line has its own. */
export function PurchaseOrderMergeCandidate({
  candidate,
  selected,
  onToggleOrder,
  onToggleLine,
}: PurchaseOrderMergeCandidateProps) {
  const { t } = useTranslation()
  const state = orderSelectionState(candidate, selected)
  return (
    <li className="flex flex-col gap-2 rounded-lg border bg-card p-3">
      <div className="flex items-start gap-2">
        <Checkbox
          className="mt-0.5"
          checked={state === 'all' ? true : state === 'some' ? 'indeterminate' : false}
          onCheckedChange={() => onToggleOrder(candidate)}
          aria-label={t('purchaseOrderActions.merge.selectOrder', { code: candidate.code })}
        />
        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1 text-xs">
          <span className="text-sm font-semibold">{candidate.code}</span>
          <span className="text-muted-foreground">{formatDate(candidate.order_date)}</span>
          <PurchaseOrderStatusBadge status={candidate.status} />
          <span className="min-w-0 flex-1 truncate" title={candidate.description}>
            {candidate.description}
          </span>
          <span className="ml-auto font-semibold tabular-nums">{formatQuoteAmount(Number(candidate.grand_total))}</span>
        </div>
      </div>
      <ul className="flex flex-col divide-y rounded-md border bg-surface text-xs">
        {candidate.lines.map((line) => (
          <li key={line.id} className="flex items-center gap-2 px-2 py-1.5">
            <Checkbox
              checked={selected.has(line.id)}
              onCheckedChange={() => onToggleLine(line.id)}
              aria-label={t('purchaseOrderActions.merge.selectLine', { description: line.description })}
            />
            <span className="min-w-0 flex-1 truncate" title={line.description}>
              {line.description}
            </span>
            <span className="tabular-nums text-muted-foreground">
              {line.quantity} x {line.unit_price}
            </span>
            <span className="w-24 text-right tabular-nums">{formatQuoteAmount(Number(line.total_amount))}</span>
          </li>
        ))}
      </ul>
    </li>
  )
}
