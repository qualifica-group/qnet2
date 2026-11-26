import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { CostReportView } from '@/features/ai-assistant-costs/cost-report-view'
import { useAiCostReport } from '@/features/ai-assistant-costs/hooks'
import { currentMonthPeriod, isValidPeriod } from '@/features/ai-assistant-costs/period'
import { PeriodFilter } from '@/features/ai-assistant-costs/period-filter'

/**
 * "AI assistant costs" page (spec 0433, super-admin only: the route sits behind the role guard and
 * the backend enforces the same rule). Figures and counts only, never conversation text.
 */
export default function AiAssistantCostsPage() {
  const { t } = useTranslation('aiAssistantCosts')
  const [period, setPeriod] = useState(() => currentMonthPeriod())
  const { data, isLoading, isError } = useAiCostReport(period)

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <PeriodFilter period={period} onChange={setPeriod} />
      {!isValidPeriod(period) ? null : isError ? (
        <p role="alert" className="text-sm text-destructive">{t('loadError')}</p>
      ) : isLoading || !data ? (
        <Skeleton className="h-64 w-full" aria-busy="true" />
      ) : (
        <CostReportView report={data} />
      )}
    </div>
  )
}
