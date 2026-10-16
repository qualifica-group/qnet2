import { useTranslation } from 'react-i18next'
import { ComparisonBarChart } from '@/components/ui/comparison-bar-chart'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { Agents } from '@/features/management-control/agents-types'
import { ExposureSection } from '@/features/management-control/exposure-table'
import { monthLabelFormatter } from '@/features/management-control/month-label'

/** Accrued against paid, per agent and per month (spec 0272 D-7). */
export function AgentsChart({ data }: { data: Agents }) {
  const { t, i18n } = useTranslation()
  const monthLabel = monthLabelFormatter(i18n.language)
  const seriesLabels = {
    budget: t('managementControl.agents.figures.accrued'),
    actual: t('managementControl.agents.figures.paid'),
  }
  const agentPoints = data.agents.map((agent) => ({ label: agent.referent.name, budget: agent.accrued, actual: agent.paid }))
  if (data.others) {
    agentPoints.push({
      label: t('managementControl.agents.table.others', { count: data.others.agents_count }),
      budget: data.others.accrued,
      actual: data.others.paid,
    })
  }

  return (
    <ExposureSection title={t('managementControl.agents.chart.title')}>
      <div className="grid grid-cols-1 items-start gap-3 lg:grid-cols-2">
        <ComparisonBarChart
          title={t('managementControl.agents.chart.agentsTitle')}
          points={agentPoints}
          seriesLabels={seriesLabels}
          formatValue={formatEuro}
          emptyLabel={t('managementControl.agents.chart.empty')}
          headingLevel={4}
        />
        <ComparisonBarChart
          title={t('managementControl.agents.chart.monthsTitle')}
          points={data.by_month.map((row) => ({ label: monthLabel(row.month), budget: row.accrued, actual: row.paid }))}
          seriesLabels={seriesLabels}
          formatValue={formatEuro}
          emptyLabel={t('managementControl.agents.chart.empty')}
          headingLevel={4}
        />
      </div>
    </ExposureSection>
  )
}
