import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { toast } from 'sonner'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { bulkDeleteTableRows } from '@/features/table/api'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { isConcludedImportRun } from '@/features/imports/lead-import-status'
import { importRunColumnRenderers } from '@/features/imports/column-renderers'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/** Domain key selecting the server-side import-runs table definition. */
const IMPORT_RUNS_DOMAIN = 'import-runs'

/**
 * Thin adapter mounting the generic table for the lead import history
 * (spec 0034: the table's own domain is now `import-runs`, the module's
 * shared entity, not `lead-imports`). It replaces the former bespoke HTML
 * table: the grid, its columns, the status badge and the per-row actions all
 * come from the backend definition. This adapter only wires the two row
 * actions to their behavior:
 *  - `view` on a concluded run (`completed`/`failed`) opens the dedicated
 *    read-only detail page; on any other (resumable) run it reopens the
 *    wizard;
 *  - `delete` removes the run through the generic bulk-delete endpoint and
 *    invalidates the module's statistics (confirmation is handled by the
 *    generic row-actions before the handler fires).
 * Permission gating is an affordance only; the backend re-authorizes every
 * call.
 */
export function LeadImportsTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const invalidateStats = useInvalidateModuleStats(IMPORT_RUNS_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        const result = await bulkDeleteTableRows(IMPORT_RUNS_DOMAIN, [Number(row.id)])
        if (result.deleted > 0) {
          toast.success(t('leadImports.deleted'))
          tableRef.current?.refresh()
          invalidateStats()
        } else {
          toast.error(t('leadImports.deleteError'))
        }
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(status === 403 ? t('leadImports.deleteForbidden') : t('leadImports.deleteError'))
      } finally {
        setDeletingId(null)
      }
    },
    [t, invalidateStats],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          if (isConcludedImportRun(String(row.status))) {
            void navigate(`/imports/${row.id}`)
          } else {
            void navigate(`/imports/new?runId=${row.id}`)
          }
          break
        case 'delete':
          void runDelete(row)
          break
        default:
          break
      }
    },
    [navigate, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <TableView
      ref={tableRef}
      domain={IMPORT_RUNS_DOMAIN}
      renderers={importRunColumnRenderers}
      onAction={handleAction}
      isBusy={isBusy}
    />
  )
}
