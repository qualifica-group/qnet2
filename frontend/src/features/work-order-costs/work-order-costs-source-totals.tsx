import { useTranslation } from 'react-i18next'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { WorkOrderCostTotals } from '@/features/work-order-costs/types'

const money = (value: string) => formatQuoteAmount(Number(value))

function SourceStat({ label, value, emphasis = false }: { label: string; value: string; emphasis?: boolean }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-lg border bg-card px-3 py-2">
      <dt className="text-[11px] text-muted-foreground">{label}</dt>
      <dd className={emphasis ? 'text-sm font-semibold tabular-nums' : 'text-sm tabular-nums'}>{money(value)}</dd>
    </div>
  )
}

/**
 * Actual cost by source including the passive cycle (spec 0252 D-3): manual
 * costs, committed ODA not yet invoiced, passive invoiced, their sum, the
 * margin on that sum and the variance from the budget.
 */
export function WorkOrderCostsSourceTotals({ totals }: { totals: WorkOrderCostTotals }) {
  const { t } = useTranslation()

  return (
    <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
      <SourceStat label={t('workOrders.costs.comparison.sources.manual')} value={totals.manual_cost_net} />
      <SourceStat label={t('workOrders.costs.comparison.sources.committed')} value={totals.committed_order_cost_net} />
      <SourceStat label={t('workOrders.costs.comparison.sources.invoiced')} value={totals.invoiced_cost_net} />
      <SourceStat label={t('workOrders.costs.comparison.sources.total')} value={totals.total_actual_cost_net} emphasis />
      <SourceStat label={t('workOrders.costs.comparison.sources.margin')} value={totals.total_actual_margin_net} emphasis />
      <SourceStat label={t('workOrders.costs.comparison.sources.delta')} value={totals.total_delta_net} emphasis />
    </dl>
  )
}
