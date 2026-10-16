import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { HoursValue } from '@/features/management-control/income-statement-hours'
import { MoneyValue } from '@/features/management-control/money-value'
import type { IncomeStatementTotals as Totals } from '@/features/management-control/types'

const TILES: readonly { field: keyof Totals; emphasis: boolean }[] = [
  { field: 'work_orders_count', emphasis: false },
  { field: 'revenue_contract', emphasis: false },
  { field: 'revenue_actual', emphasis: false },
  { field: 'cost_budget', emphasis: false },
  { field: 'cost_actual', emphasis: false },
  { field: 'cost_labor', emphasis: false },
  { field: 'labor_minutes', emphasis: false },
  { field: 'commission_actual', emphasis: false },
  { field: 'margin_budget', emphasis: true },
  { field: 'margin_actual', emphasis: true },
  { field: 'margin_delta', emphasis: true },
]

/** Counts and hours read as plain numbers, every other tile is an amount. */
function TileValue({ field, totals }: { field: keyof Totals; totals: Totals }) {
  if (field === 'work_orders_count') {
    return <span className="tabular-nums">{totals[field]}</span>
  }
  if (field === 'labor_minutes') {
    return <HoursValue laborMinutes={totals.labor_minutes} unpricedMinutes={totals.unpriced_minutes} />
  }
  return <MoneyValue value={totals[field]} />
}

/**
 * Compact KPI tiles with the totals of the selected work orders (spec 0268, labor 0272). Eleven tiles:
 * the hours tile spans two columns on every width, so 2 columns (mobile) and 4 columns (sm) both close without orphans.
 */
export function IncomeStatementTotals({ totals }: { totals: Totals }) {
  const { t } = useTranslation()

  return (
    <section aria-label={t('managementControl.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {TILES.map(({ field, emphasis }) => (
          <div
            key={field}
            className={cn(
              'flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3',
              field === 'labor_minutes' && 'col-span-2',
            )}
          >
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.figures.${field}`)}</dt>
            <dd className={emphasis ? 'text-base font-semibold' : 'text-sm font-medium'}>
              <TileValue field={field} totals={totals} />
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
