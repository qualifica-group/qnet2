import { useCallback, useRef } from 'react'
import { PageHeader } from '@/components/page-header'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { AgentSalesReportExportMenu } from '@/features/agent-sales-report/export-menu'
import { agentSalesReportRenderers } from '@/features/agent-sales-report/column-renderers'
import { AGENT_SALES_REPORT_DOMAIN } from '@/features/agent-sales-report/api'
import { agentSalesReportActionHref } from '@/features/agent-sales-report/action-href'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableExportMenuContext } from '@/features/table/table-view-props'

function renderExportMenu(context: TableExportMenuContext) {
  return <AgentSalesReportExportMenu context={context} />
}

/**
 * Thin adapter over the generic table (spec 0365 D-4): domain
 * `agent-sales-report`, no creation, editing or bulk deletion; filters, saved
 * views and the row count come from the framework. The `view` row action opens
 * the offer in a modal over the report, whatever the Offerte open-mode
 * preference (user directive 2026-11-12); Ctrl/Cmd+click still opens its page
 * in a new tab.
 */
export function AgentSalesReportTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => {
    tableRef.current?.refresh()
  }, [])
  const { openView, sheet } = useModuleOpener(QUOTES_DOMAIN, { forceMode: OPEN_MODE_MODAL, onSaved: refreshGrid })

  const handleAction: RowActionHandler = (action, row) => {
    if (agentSalesReportActionHref(action, row) !== undefined) {
      openView(row)
    }
  }

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <TableView
        ref={tableRef}
        domain={AGENT_SALES_REPORT_DOMAIN}
        renderers={agentSalesReportRenderers}
        onAction={handleAction}
        resolveActionHref={agentSalesReportActionHref}
        exportMenu={renderExportMenu}
        disableBuiltinDelete
      />
      {sheet}
    </div>
  )
}
