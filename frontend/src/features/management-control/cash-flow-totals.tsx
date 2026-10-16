import { useTranslation } from 'react-i18next'
import type { CashFlow } from '@/features/management-control/cash-flow-types'
import { applyBacklogScenario, horizonTotals } from '@/features/management-control/cash-flow-scenario'
import { MoneyValue } from '@/features/management-control/money-value'

interface Tile {
  key: string
  emphasis: boolean
  value: number
}

function tilesOf(data: CashFlow): Tile[] {
  const horizon = horizonTotals(data)
  const withScenario = horizonTotals(applyBacklogScenario(data))
  return [
    { key: 'backlog_net', emphasis: false, value: data.backlog.net },
    { key: 'receivable', emphasis: false, value: horizon.receivable },
    { key: 'payable', emphasis: false, value: horizon.payable },
    { key: 'committed', emphasis: false, value: horizon.committed },
    { key: 'net', emphasis: true, value: horizon.net },
    { key: 'scenario_net', emphasis: true, value: withScenario.net },
  ]
}

/** Six KPI tiles (spec 0270): net backlog, receipts, payments and commitments of the horizon, balance with and without scenario. */
export function CashFlowTotals({ data }: { data: CashFlow }) {
  const { t } = useTranslation()

  return (
    <section aria-label={t('managementControl.cashFlow.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {tilesOf(data).map(({ key, emphasis, value }) => (
          <div key={key} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.cashFlow.totals.${key}`)}</dt>
            <dd className={emphasis ? 'text-base font-semibold' : 'text-sm font-medium'}>
              <MoneyValue value={value} />
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
