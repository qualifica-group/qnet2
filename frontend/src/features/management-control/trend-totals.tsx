import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatVariation, sumMoney, variationPercent } from '@/features/management-control/money-math'
import { MoneyValue } from '@/features/management-control/money-value'
import { TREND_MEASURES, type MonthlyTrend } from '@/features/management-control/trend-types'

/** Three KPI tiles (spec 0270): revenue, costs and margin of the period, with the change on the previous year. */
export function TrendTotals({ data }: { data: MonthlyTrend }) {
  const { t, i18n } = useTranslation()

  return (
    <section aria-label={t('managementControl.trend.totals.title')}>
      <dl className="grid grid-cols-1 gap-2 sm:grid-cols-3">
        {TREND_MEASURES.map((measure) => {
          const current = sumMoney(data.totals[measure])
          const previous = sumMoney(data.totals.previous_year[measure])
          const variation = variationPercent(current, previous)

          return (
            <div key={measure} className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-card p-3">
              <dt className="line-clamp-2 text-xs text-muted-foreground">{t(`managementControl.trend.measures.${measure}`)}</dt>
              <dd className="text-base font-semibold">
                <MoneyValue value={current} />
              </dd>
              <dd className="text-xs text-muted-foreground">
                {t('managementControl.trend.totals.previousYear', { value: formatEuro(previous) })}
                {' - '}
                <span className="tabular-nums">
                  {variation === null ? t('managementControl.trend.notAvailable') : formatVariation(variation, i18n.language)}
                </span>{' '}
                {t('managementControl.trend.totals.variation')}
              </dd>
            </div>
          )
        })}
      </dl>
    </section>
  )
}
