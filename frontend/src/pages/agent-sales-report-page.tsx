import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AgentSalesReportTable } from '@/features/agent-sales-report/agent-sales-report-table'
import { AGENT_SALES_REPORT_PERMISSION } from '@/features/agent-sales-report/api'

/**
 * Agent Sales Report page (spec 0365). Light composition only: gates access
 * with `agent-sales-report.view` (the backend re-authorizes every endpoint and
 * scopes the quotes to the actor's visibility) and mounts the grid adapter.
 */
export default function AgentSalesReportPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission={AGENT_SALES_REPORT_PERMISSION}
      fallback={<p className="text-sm text-muted-foreground">{t('agentSalesReport.forbidden')}</p>}
    >
      <AgentSalesReportTable />
    </Can>
  )
}
