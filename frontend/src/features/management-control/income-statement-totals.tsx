import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { MoneyValue } from '@/features/management-control/money-value'
import type { IncomeStatementTotals as Totals } from '@/features/management-control/types'

const TILES: readonly { field: keyof Totals; emphasis: boolean }[] = [
  { field: 'work_orders_count', emphasis: false },
  { field: 'revenue_contract', emphasis: false },
  { field: 'revenue_actual', emphasis: false },
  { field: 'cost_budget', emphasis: false },
  { field: 'cost_actual', emphasis: false },
  { field: 'commission_actual', emphasis: false },
  { field: 'margin_budget', emphasis: true },
  { field: 'margin_actual', emphasis: true },
  { field: 'margin_delta', emphasis: true },
]

/** Compact KPI tiles with the totals of the selected work orders (spec 0268). */
export function IncomeStatementTotals({ totals }: { totals: Totals }) {
  const { t } = useTranslation()

  return (
    <section aria-label={t('managementControl.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {TILES.map(({ field, emphasis }) => (
          <div
            key={field}
            className={cn(
              'flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3',
              field === 'work_orders_count' && 'col-span-2 sm:col-span-1',
            )}
          >
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.figures.${field}`)}</dt>
            <dd className={emphasis ? 'text-base font-semibold' : 'text-sm font-medium'}>
              {field === 'work_orders_count' ? (
                <span className="tabular-nums">{totals[field]}</span>
              ) : (
                <MoneyValue value={totals[field]} />
              )}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
