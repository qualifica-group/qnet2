import { useTranslation } from 'react-i18next'
import { ComparisonBarChart } from '@/components/ui/comparison-bar-chart'
import { StatColumnChart } from '@/components/ui/stat-column-chart'
import { CHART_SERIES_COLORS } from '@/components/ui/stat-chart-tokens'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { AccountFlows } from '@/features/management-control/accounts-types'
import { ExposureSection } from '@/features/management-control/exposure-table'
import { monthLabelFormatter } from '@/features/management-control/month-label'

/** Width of the value axis that fits an amount in euro (the default clips it). */
const VALUE_AXIS_WIDTH = 80

const SIDES = ['collections', 'payments'] as const

/**
 * One colour per account over the accounts of both sides, so the same account looks the same in
 * both charts; the unattributed bucket takes the last colour.
 */
function accountColors(data: AccountFlows): Map<number, string> {
  const ids = [...new Set(SIDES.flatMap((side) => data[side].by_account.flatMap((row) => (row.account ? [row.account.id] : []))))]
  return new Map(ids.map((id, index) => [id, CHART_SERIES_COLORS[index % CHART_SERIES_COLORS.length]]))
}

/** Receipts against payments per month, and the amount of each financial account (spec 0271 D-10). */
export function AccountsChart({ data }: { data: AccountFlows }) {
  const { t, i18n } = useTranslation()
  const monthLabel = monthLabelFormatter(i18n.language)
  const colors = accountColors(data)
  const unattributedColor = CHART_SERIES_COLORS[CHART_SERIES_COLORS.length - 1]

  return (
    <ExposureSection title={t('managementControl.accounts.chart.title')}>
      <ComparisonBarChart
        title={t('managementControl.accounts.chart.monthsTitle')}
        points={data.by_month.map((row) => ({ label: monthLabel(row.month), budget: row.collected, actual: row.paid }))}
        seriesLabels={{
          budget: t('managementControl.accounts.totals.collections'),
          actual: t('managementControl.accounts.totals.payments'),
        }}
        formatValue={formatEuro}
        emptyLabel={t('managementControl.accounts.chart.empty')}
        headingLevel={4}
      />
      <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
        {SIDES.map((side) => (
          <StatColumnChart
            key={side}
            title={t(`managementControl.accounts.chart.${side}Title`)}
            items={data[side].by_account.map((row) => ({
              key: String(row.account?.id ?? 'unattributed'),
              label: row.account?.name ?? t('managementControl.unattributed'),
              value: row.amount,
              color: (row.account && colors.get(row.account.id)) || unattributedColor,
            }))}
            formatValue={formatEuro}
            emptyLabel={t('managementControl.accounts.chart.empty')}
            headingLevel={4}
            valueAxisWidth={VALUE_AXIS_WIDTH}
          />
        ))}
      </div>
    </ExposureSection>
  )
}
