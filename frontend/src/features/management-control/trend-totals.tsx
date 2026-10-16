import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatHours } from '@/features/management-control/exposure-format'
import { formatVariation, sumMoney, variationPercent } from '@/features/management-control/money-math'
import { MoneyValue } from '@/features/management-control/money-value'
import { TREND_MEASURES, type MonthlyTrend } from '@/features/management-control/trend-types'

const sumMinutes = (values: readonly number[]): number => values.reduce((total, value) => total + value, 0)

/**
 * Four KPI tiles (spec 0270, labor 0272): revenue, costs, labor cost and margin of the period, with the change on the
 * previous year; the labor tile adds the hours recorded and the part not valued. 2 columns (mobile) and 4 (sm) leave no orphan.
 */
export function TrendTotals({ data }: { data: MonthlyTrend }) {
  const { t, i18n } = useTranslation()

  return (
    <section aria-label={t('managementControl.trend.totals.title')}>
      <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {TREND_MEASURES.map((measure) => {
          const current = sumMoney(data.totals[measure])
          const previous = sumMoney(data.totals.previous_year[measure])
          const variation = variationPercent(current, previous)
          const unpricedMinutes = sumMinutes(data.totals.unpriced_minutes)

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
              {measure === 'labor' ? (
                <dd className="text-xs text-muted-foreground">
                  <span className="tabular-nums">
                    {t('managementControl.trend.totals.hours', { hours: formatHours(sumMinutes(data.totals.labor_minutes)) })}
                  </span>
                  {unpricedMinutes > 0 ? (
                    <span className="block" title={t('managementControl.trend.totals.unpricedHint')}>
                      {t('managementControl.trend.totals.unpriced', { hours: formatHours(unpricedMinutes) })}
                    </span>
                  ) : null}
                </dd>
              ) : null}
            </div>
          )
        })}
      </dl>
    </section>
  )
}
