import { useTranslation } from 'react-i18next'
import { FileSpreadsheet, Loader2 } from 'lucide-react'
import { DropdownMenuItem, DropdownMenuLabel } from '@/components/ui/dropdown-menu'
import { useAgentSalesReportExport } from '@/features/agent-sales-report/use-agent-sales-report-export'
import type { AgentSalesReportExportKind } from '@/features/agent-sales-report/api'
import type { TableExportMenuContext } from '@/features/table/table-view-props'

/**
 * The report's three exports as toolbar menu entries (spec 0365 D-8/D-9):
 * Sommario opens the generic export dialog; Dettagli and Righe dettaglio
 * download the workbook straight away with the grid's current state.
 */
export function AgentSalesReportExportMenu({ context }: { context: TableExportMenuContext }) {
  const { t } = useTranslation()
  const { run, isPending, pendingKind } = useAgentSalesReportExport(context)

  const downloadItem = (kind: AgentSalesReportExportKind, label: string) => (
    <DropdownMenuItem
      disabled={isPending}
      onSelect={(event) => {
        event.preventDefault()
        run(kind)
      }}
    >
      {isPending && pendingKind === kind ? (
        <Loader2 className="animate-spin" aria-hidden="true" />
      ) : (
        <FileSpreadsheet aria-hidden="true" />
      )}
      {label}
    </DropdownMenuItem>
  )

  return (
    <>
      <DropdownMenuLabel className="text-[11px] font-medium text-muted-foreground">
        {t('agentSalesReport.export.menu')}
      </DropdownMenuLabel>
      <DropdownMenuItem
        onSelect={(event) => {
          event.preventDefault()
          context.openGenericExport()
        }}
      >
        <FileSpreadsheet aria-hidden="true" />
        {t('agentSalesReport.export.summary')}
      </DropdownMenuItem>
      {downloadItem('details', t('agentSalesReport.export.details'))}
      {downloadItem('lines', t('agentSalesReport.export.lines'))}
    </>
  )
}
