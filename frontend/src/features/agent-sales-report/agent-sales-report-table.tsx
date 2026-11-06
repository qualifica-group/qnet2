import { PageHeader } from '@/components/page-header'
import { TableView } from '@/features/table/table-view'
import { AgentSalesReportExportMenu } from '@/features/agent-sales-report/export-menu'
import { agentSalesReportRenderers } from '@/features/agent-sales-report/column-renderers'
import { AGENT_SALES_REPORT_DOMAIN } from '@/features/agent-sales-report/api'
import type { TableExportMenuContext } from '@/features/table/table-view-props'

/** The report is read-only: the catalog declares no row action, so nothing ever reaches this handler. */
function ignoreAction(): void {}

function renderExportMenu(context: TableExportMenuContext) {
  return <AgentSalesReportExportMenu context={context} />
}

/**
 * Thin adapter over the generic table (spec 0365 D-4): domain
 * `agent-sales-report`, no creation, editing or bulk deletion; filters, saved
 * views and the row count come from the framework.
 */
export function AgentSalesReportTable() {
  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <TableView
        domain={AGENT_SALES_REPORT_DOMAIN}
        renderers={agentSalesReportRenderers}
        onAction={ignoreAction}
        exportMenu={renderExportMenu}
        disableBuiltinDelete
      />
    </div>
  )
}
