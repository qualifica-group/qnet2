import { useTranslation } from 'react-i18next'
import { TriangleAlert } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

const money = (value: string) => formatQuoteAmount(Number(value))
const isOverrun = (delta: string) => Number(delta) > 0

interface DeltaCellProps {
  value: string
}

/** The variance never relies on colour alone: an overrun also carries an icon and a visible label. */
function DeltaCell({ value }: DeltaCellProps) {
  const { t } = useTranslation()
  const overrun = isOverrun(value)

  return (
    <span className={cn('inline-flex items-center justify-end gap-1 tabular-nums', overrun && 'font-medium text-destructive')}>
      {overrun ? (
        <>
          <TriangleAlert aria-hidden="true" className="size-3.5" />
          <span className="text-[11px]">{t('workOrders.costs.comparison.overrun')}</span>
        </>
      ) : null}
      {money(value)}
    </span>
  )
}

function Stat({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-lg border bg-card px-3 py-2">
      <dt className="text-[11px] text-muted-foreground">{label}</dt>
      <dd className="text-sm font-semibold tabular-nums">{children}</dd>
    </div>
  )
}

/**
 * Budget vs actual (spec 0190 D-1/D-5): totals strip, one row per revenue
 * line of the work order, the costs with no revenue line, and the generic
 * offer costs that are informative only and never enter the totals.
 */
export function WorkOrderCostsComparisonTab({ overview }: { overview: WorkOrderCostOverview }) {
  const { t } = useTranslation()
  const { comparison, budget } = overview
  const { totals } = comparison

  return (
    <div className="flex flex-col gap-3">
      <dl className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
        <Stat label={t('workOrders.costs.comparison.revenue')}>{money(totals.revenue_net)}</Stat>
        <Stat label={t('workOrders.costs.comparison.budgetCost')}>{money(totals.budget_cost_net)}</Stat>
        <Stat label={t('workOrders.costs.comparison.actualCost')}>{money(totals.actual_cost_net)}</Stat>
        <Stat label={t('workOrders.costs.comparison.delta')}>
          <DeltaCell value={totals.delta_net} />
        </Stat>
        <Stat label={t('workOrders.costs.comparison.budgetMargin')}>{money(totals.budget_margin_net)}</Stat>
        <Stat label={t('workOrders.costs.comparison.actualMargin')}>{money(totals.actual_margin_net)}</Stat>
      </dl>

      <div className="overflow-x-auto rounded-lg border bg-surface">
        <table className="w-full min-w-[640px] text-xs">
          <caption className="sr-only">{t('workOrders.costs.comparison.tableCaption')}</caption>
          <thead className="bg-muted/40 text-[11px] text-muted-foreground">
            <tr>
              <th scope="col" className="px-2 py-1.5 text-left font-medium">{t('workOrders.costs.comparison.revenueLine')}</th>
              <th scope="col" className="px-2 py-1.5 text-right font-medium">{t('workOrders.costs.comparison.revenue')}</th>
              <th scope="col" className="px-2 py-1.5 text-right font-medium">{t('workOrders.costs.comparison.budgetCost')}</th>
              <th scope="col" className="px-2 py-1.5 text-right font-medium">{t('workOrders.costs.comparison.actualCost')}</th>
              <th scope="col" className="px-2 py-1.5 text-right font-medium">{t('workOrders.costs.comparison.delta')}</th>
            </tr>
          </thead>
          <tbody>
            {comparison.rows.map((row) => (
              <tr key={row.quote_line_id} className="border-t">
                <th scope="row" className="max-w-0 truncate px-2 py-1.5 text-left font-normal">
                  <span className="font-mono text-muted-foreground">{row.product.code}</span> {row.product.name}
                </th>
                <td className="px-2 py-1.5 text-right tabular-nums">{money(row.revenue_net)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{money(row.budget_cost_net)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{money(row.actual_cost_net)}</td>
                <td className="px-2 py-1.5 text-right"><DeltaCell value={row.delta_net} /></td>
              </tr>
            ))}
            <tr className="border-t bg-muted/40">
              <th scope="row" className="px-2 py-1.5 text-left font-normal text-muted-foreground">
                {t('workOrders.costs.comparison.unattributed')}
              </th>
              <td className="px-2 py-1.5 text-right tabular-nums">{money('0')}</td>
              <td className="px-2 py-1.5 text-right tabular-nums">{money('0')}</td>
              <td className="px-2 py-1.5 text-right tabular-nums">{money(comparison.unattributed_actual_cost_net)}</td>
              <td className="px-2 py-1.5 text-right"><DeltaCell value={comparison.unattributed_actual_cost_net} /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <details className="rounded-lg border bg-surface text-xs">
        <summary className="cursor-pointer px-3 py-2 font-medium">
          {t('workOrders.costs.comparison.unallocatedTitle', { count: budget.unallocated_lines.length })}
        </summary>
        <div className="border-t px-3 py-2">
          <p className="mb-2 text-muted-foreground">{t('workOrders.costs.comparison.unallocatedHint')}</p>
          {budget.unallocated_lines.length === 0 ? (
            <p className="text-muted-foreground">{t('workOrders.costs.comparison.unallocatedEmpty')}</p>
          ) : (
            <ul className="flex flex-col gap-1">
              {budget.unallocated_lines.map((line) => (
                <li key={line.id} className="flex items-center justify-between gap-2">
                  <span className="truncate">
                    <span className="font-mono text-muted-foreground">{line.product.code}</span> {line.product.name}
                  </span>
                  <span className="tabular-nums">{money(line.net_amount)}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </details>
    </div>
  )
}
