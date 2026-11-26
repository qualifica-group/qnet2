import { useTranslation } from 'react-i18next'
import { ReportBarChart } from '@/components/ui/report-bar-chart'
import { StatCard } from '@/components/ui/stat-card'
import { TrendLineChart } from '@/components/ui/trend-line-chart'
import { FunctionCostTable, ModelCostTable, UserCostTable } from '@/features/ai-assistant-costs/cost-tables'
import { formatCount, formatUsd, formatUsdNumber } from '@/features/ai-assistant-costs/format'
import type { AiCostCaps, AiCostReport } from '@/features/ai-assistant-costs/types'

function CapsCard({ caps }: { caps: AiCostCaps }) {
  const { t } = useTranslation('aiAssistantCosts')
  const cap = (value: string | null) => (value === null ? t('caps.noCap') : formatUsd(value))

  return (
    <section aria-label={t('caps.title')} className="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <StatCard label={t('caps.spent')} value={formatUsd(caps.spent_total_usd)} subtitle={`${t('caps.month')} ${caps.month}`} />
      <StatCard label={t('caps.capTotal')} value={cap(caps.spend_cap_total_usd)} />
      <StatCard label={t('caps.capPerson')} value={cap(caps.spend_cap_person_usd)} />
    </section>
  )
}

/** Tiles, charts and tables of one report. Charts load lazily (recharts stays out of the page chunk). */
export function CostReportView({ report }: { report: AiCostReport }) {
  const { t } = useTranslation('aiAssistantCosts')
  const { totals } = report

  return (
    <div className="flex flex-col gap-4">
      <section aria-label={t('totals.cost')} className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        <StatCard className="col-span-2 lg:col-span-1" label={t('totals.cost')} value={formatUsd(totals.cost_usd)} />
        <StatCard label={t('totals.requests')} value={formatCount(totals.requests)} />
        <StatCard label={t('totals.inputTokens')} value={formatCount(totals.input_tokens)} />
        <StatCard label={t('totals.outputTokens')} value={formatCount(totals.output_tokens)} />
        <StatCard label={t('totals.cacheRead')} value={formatCount(totals.cache_read_tokens)} />
        <StatCard label={t('totals.reasoning')} value={formatCount(totals.reasoning_tokens)} />
      </section>
      <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <TrendLineChart
          title={t('charts.byDay')}
          months={report.by_day.map((day) => day.date)}
          series={[{ key: 'cost', label: t('tables.cost'), values: report.by_day.map((day) => Number(day.cost_usd)) }]}
          formatValue={formatUsdNumber}
          emptyLabel={t('charts.empty')}
        />
        <ReportBarChart
          title={t('charts.byFunction')}
          points={report.by_function.map((row) => ({ label: row.name ?? t('tables.noFunction'), value: Number(row.cost_usd) }))}
          formatValue={formatUsdNumber}
          emptyLabel={t('charts.empty')}
        />
      </div>
      <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
        <FunctionCostTable rows={report.by_function} />
        <ModelCostTable rows={report.by_model} />
      </div>
      <UserCostTable rows={report.by_user} />
      <CapsCard caps={report.caps} />
    </div>
  )
}
