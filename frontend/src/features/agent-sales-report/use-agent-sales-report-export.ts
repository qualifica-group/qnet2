import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { buildExportGridState } from '@/features/exports/build-export-grid-state'
import {
  downloadAgentSalesReportExport,
  readErrorMessage,
  type AgentSalesReportExportKind,
} from '@/features/agent-sales-report/api'
import type { TableExportMenuContext } from '@/features/table/table-view-props'

/**
 * Runs the Dettagli / Righe dettaglio downloads with the grid's current state
 * and reports failures as a toast (the server message carries the reason, e.g. too many quotes).
 */
export function useAgentSalesReportExport(context: TableExportMenuContext) {
  const { t } = useTranslation()
  const { gridApi, columns, search, advancedFilters, customFilterRules } = context

  const mutation = useMutation({
    mutationFn: (kind: AgentSalesReportExportKind) => {
      // Step 1: freeze the grid state (sort, filters, search) as the export contract expects it
      const state = gridApi ? buildExportGridState({ gridApi, columns, search, t }) : null
      // Step 2: ask the server for the workbook and save it
      return downloadAgentSalesReportExport(kind, {
        sortModel: state?.sortModel ?? [],
        filterModel: state?.filterModel ?? {},
        search: state?.search ? state.search : null,
        advancedFilters,
        customFilterRules,
      })
    },
    onError: async (error) => {
      // The server answers with an already translated message (e.g. too many quotes).
      toast.error((await readErrorMessage(error)) ?? t('agentSalesReport.export.error'))
    },
  })

  return { run: mutation.mutate, isPending: mutation.isPending, pendingKind: mutation.variables }
}
