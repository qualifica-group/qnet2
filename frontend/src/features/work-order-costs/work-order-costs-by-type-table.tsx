import { useTranslation } from 'react-i18next'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { WorkOrderCostByType } from '@/features/work-order-costs/types'

const money = (value: string) => formatQuoteAmount(Number(value))
const NUMBER_HEADERS = ['manual', 'committed', 'invoiced', 'total'] as const

/**
 * Actual costs per cost type and source (spec 0252 D-3). Only the actual is
 * shown: the budget is not split by cost type (D-8).
 */
export function WorkOrderCostsByTypeTable({ rows }: { rows: WorkOrderCostByType[] }) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-2" aria-labelledby="work-order-costs-by-type-title">
      <h3 id="work-order-costs-by-type-title" className="text-sm font-semibold">
        {t('workOrders.costs.comparison.byType.title')}
      </h3>
      {rows.length === 0 ? (
        <p className="rounded-lg border bg-surface px-3 py-2 text-xs text-muted-foreground">
          {t('workOrders.costs.comparison.byType.empty')}
        </p>
      ) : (
        <div className="overflow-x-auto rounded-lg border bg-surface">
          <table className="w-full min-w-[640px] text-xs">
            <caption className="sr-only">{t('workOrders.costs.comparison.byType.caption')}</caption>
            <thead className="bg-muted/40 text-[11px] text-muted-foreground">
              <tr>
                <th scope="col" className="px-2 py-1.5 text-left font-medium">{t('workOrders.costs.comparison.byType.costType')}</th>
                <th scope="col" className="px-2 py-1.5 text-left font-medium">{t('workOrders.costs.comparison.byType.nature')}</th>
                {NUMBER_HEADERS.map((key) => (
                  <th key={key} scope="col" className="px-2 py-1.5 text-right font-medium">
                    {t(`workOrders.costs.comparison.byType.${key}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.cost_type?.id ?? 'none'} className="border-t">
                  {row.cost_type ? (
                    <>
                      <th scope="row" className="px-2 py-1.5 text-left font-normal">
                        <CostTypeBadge name={row.cost_type.name} color={row.cost_type.color} />
                      </th>
                      <td className="px-2 py-1.5">{t(`costTypes.nature.${row.cost_type.nature}`)}</td>
                    </>
                  ) : (
                    <>
                      <th scope="row" className="px-2 py-1.5 text-left font-normal text-muted-foreground">
                        {t('workOrders.costs.comparison.byType.noType')}
                      </th>
                      <td className="px-2 py-1.5 text-muted-foreground">-</td>
                    </>
                  )}
                  <td className="px-2 py-1.5 text-right tabular-nums">{money(row.manual_cost_net)}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">{money(row.committed_order_cost_net)}</td>
                  <td className="px-2 py-1.5 text-right tabular-nums">{money(row.invoiced_cost_net)}</td>
                  <td className="px-2 py-1.5 text-right font-medium tabular-nums">{money(row.total_cost_net)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
