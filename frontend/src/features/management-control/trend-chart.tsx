import { useTranslation } from 'react-i18next'
import { TrendLineChart } from '@/components/ui/trend-line-chart'
import { formatEuro } from '@/features/invoices/invoice-format'
import { monthLabelFormatter } from '@/features/management-control/month-label'
import { trendRowLabel } from '@/features/management-control/trend-row-label'
import type { MonthlyTrend, TrendMeasure } from '@/features/management-control/trend-types'

/** One line per business function on the chosen measure, and the totals against the previous year (spec 0270 D-8, D-12). */
export function TrendChart({ data, measure }: { data: MonthlyTrend; measure: TrendMeasure }) {
  const { t, i18n } = useTranslation()
  const months = data.months.map(monthLabelFormatter(i18n.language))
  const measureLabel = t(`managementControl.trend.measures.${measure}`)

  return (
    <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
      <TrendLineChart
        title={t('managementControl.trend.functions.chartTitle', { measure: measureLabel })}
        months={months}
        series={data.functions.map((row) => ({ key: row.key, label: trendRowLabel(t, row), values: row[measure] }))}
        formatValue={formatEuro}
        headingLevel={4}
      />
      <TrendLineChart
        title={t('managementControl.trend.functions.comparisonTitle', { measure: measureLabel })}
        months={months}
        series={[
          { key: 'current', label: t('managementControl.trend.functions.seriesCurrent'), values: data.totals[measure] },
          { key: 'previous', label: t('managementControl.trend.functions.seriesPrevious'), values: data.totals.previous_year[measure] },
        ]}
        formatValue={formatEuro}
        headingLevel={4}
      />
    </div>
  )
}
