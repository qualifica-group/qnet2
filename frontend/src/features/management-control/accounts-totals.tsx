import { useTranslation } from 'react-i18next'
import type { AccountFlows } from '@/features/management-control/accounts-types'
import { MoneyValue } from '@/features/management-control/money-value'
import { roundMoney } from '@/features/management-control/money-math'

/** Three KPI tiles (spec 0271): receipts and payments of the period, and their difference. */
export function AccountsTotals({ data }: { data: AccountFlows }) {
  const { t } = useTranslation()
  const tiles = [
    { key: 'collections', value: data.collections.total, emphasis: false },
    { key: 'payments', value: data.payments.total, emphasis: false },
    { key: 'net', value: roundMoney(data.collections.total - data.payments.total), emphasis: true },
  ] as const

  return (
    <section aria-label={t('managementControl.accounts.totals.title')}>
      <dl className="grid grid-cols-1 gap-2 sm:grid-cols-3">
        {tiles.map(({ key, value, emphasis }) => (
          <div key={key} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.accounts.totals.${key}`)}</dt>
            <dd className={emphasis ? 'text-base font-semibold' : 'text-sm font-medium'}>
              <MoneyValue value={value} />
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
