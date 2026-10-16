import { useTranslation } from 'react-i18next'
import { MoneyValue } from '@/features/management-control/money-value'
import type { Exposure } from '@/features/management-control/exposure-types'

interface Tile {
  key: string
  emphasis: boolean
  money?: number
  days?: number | null
}

function tilesOf(data: Exposure): Tile[] {
  return [
    { key: 'receivable_open', emphasis: false, money: data.receivable.open_total },
    { key: 'receivable_overdue', emphasis: false, money: data.receivable.overdue_total },
    { key: 'payable_open', emphasis: false, money: data.payable.open_total },
    { key: 'payable_overdue', emphasis: false, money: data.payable.overdue_total },
    { key: 'committed', emphasis: false, money: data.committed.total },
    { key: 'net', emphasis: true, money: data.exposure.net },
    { key: 'dso', emphasis: false, days: data.receivable.average_days },
    { key: 'dpo', emphasis: false, days: data.payable.average_days },
  ]
}

/** Eight KPI tiles (spec 0269): open and overdue on both sides, committed, net exposure, DSO and DPO. */
export function ExposureTotals({ data }: { data: Exposure }) {
  const { t } = useTranslation()

  return (
    <section aria-label={t('managementControl.exposure.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {tilesOf(data).map(({ key, emphasis, money, days }) => (
          <div key={key} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
            <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.exposure.totals.${key}`)}</dt>
            <dd className={emphasis ? 'text-base font-semibold' : 'text-sm font-medium'}>
              {money !== undefined ? (
                <MoneyValue value={money} />
              ) : (
                <span className="tabular-nums">
                  {days === null || days === undefined
                    ? t('managementControl.exposure.totals.notAvailable')
                    : t('managementControl.exposure.totals.days', { count: days })}
                </span>
              )}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
