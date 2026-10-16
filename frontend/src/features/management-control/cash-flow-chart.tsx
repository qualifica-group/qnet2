import { useTranslation } from 'react-i18next'
import { ComparisonBarChart } from '@/components/ui/comparison-bar-chart'
import { StatChart } from '@/components/ui/stat-chart'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { CashFlow, Flow } from '@/features/management-control/cash-flow-types'
import { roundMoney } from '@/features/management-control/money-math'
import { monthLabelFormatter } from '@/features/management-control/month-label'

/** Payments for the chart: what is owed on invoices plus what is committed on orders. */
function outflow(flow: Flow): number {
  return roundMoney(flow.payable + flow.committed)
}

/** Receipts against payments and commitments per column, and the cumulative net of the months (spec 0270 D-12). */
export function CashFlowChart({ data }: { data: CashFlow }) {
  const { t, i18n } = useTranslation()
  const monthLabel = monthLabelFormatter(i18n.language)

  const columns = [
    { label: t('managementControl.cashFlow.table.backlog'), flow: data.backlog },
    ...data.months.map((month) => ({ label: monthLabel(month.month), flow: month })),
    { label: t('managementControl.cashFlow.table.beyond'), flow: data.beyond },
  ]

  return (
    <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
      <ComparisonBarChart
        title={t('managementControl.cashFlow.chart.title')}
        points={columns.map(({ label, flow }) => ({ label, budget: flow.receivable, actual: outflow(flow) }))}
        seriesLabels={{
          budget: t('managementControl.cashFlow.chart.seriesReceipts'),
          actual: t('managementControl.cashFlow.chart.seriesPayments'),
        }}
        formatValue={formatEuro}
        headingLevel={4}
      />
      <StatChart
        title={t('managementControl.cashFlow.chart.cumulativeTitle')}
        points={data.months.map((month) => ({ label: monthLabel(month.month), value: month.cumulative }))}
        formatValue={formatEuro}
        variant="line"
        headingLevel={4}
      />
    </div>
  )
}
