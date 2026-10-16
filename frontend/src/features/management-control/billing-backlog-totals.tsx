import { useTranslation } from 'react-i18next'
import { MoneyValue } from '@/features/management-control/money-value'
import type { Billing } from '@/features/management-control/billing-types'

/** Four KPI tiles (spec 0271): open work orders, contract, invoiced and still to invoice. */
export function BillingBacklogTotals({ backlog }: { backlog: Billing['backlog'] }) {
  const { t } = useTranslation()
  const money = [
    { key: 'contract', value: backlog.contract, emphasis: false },
    { key: 'invoiced', value: backlog.invoiced, emphasis: false },
    { key: 'to_invoice', value: backlog.to_invoice, emphasis: true },
  ] as const

  return (
    <section aria-label={t('managementControl.billing.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
          <dt className="line-clamp-2 text-xs text-muted-foreground">{t('managementControl.billing.totals.work_orders_count')}</dt>
          <dd className="text-sm font-medium tabular-nums">{backlog.work_orders_count}</dd>
        </div>
        {money.map(({ key, value, emphasis }) => (
          <div key={key} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.billing.totals.${key}`)}</dt>
            <dd className={emphasis ? 'text-base font-semibold' : 'text-sm font-medium'}>
              <MoneyValue value={value} />
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
