import { useCallback, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { deleteQuote, QUOTES_DOMAIN } from '@/features/quotes/api'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { TableViewHandle } from '@/features/table/table-view'

export interface UseOpportunityQuotesPanelResult {
  tableRef: React.RefObject<TableViewHandle | null>
  /** Live counter (AC-031/D-9): the grid's own total once reported, `quotesCount` until then. */
  count: number
  /**
   * AC-032/D-9: true when the panel has never been known to hold a Quote for
   * this opportunity — renders the empty state INSTEAD OF the grid. Never
   * flips back to true after a delete: once the grid has mounted, a
   * zero-row result (filtered or not) is AG Grid's own empty overlay to
   * show, not this component's.
   */
  showEmptyState: boolean
  isBusy: (row: TableRow) => boolean
  handleAction: RowActionHandler
  handleRowCountChanged: (count: number | null) => void
  handleCreate: () => void
  activityRow: TableRow | null
  closeActivity: (open: boolean) => void
  sheet: ReactNode
}

/**
 * Orchestrates the Opportunity detail's Quotes panel (spec 0067): the grid
 * refresh handle, the empty-state/counter derivation (D-9), the forced-Sheet
 * open flow (D-3) and the delete confirmation/toast flow — mirrors
 * `QuotesTable`'s own hookless logic, factored out here so the panel
 * component stays presentation-only (engineering.md §2).
 */
export function useOpportunityQuotesPanel(
  opportunityId: number,
  quotesCount: number,
): UseOpportunityQuotesPanelResult {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [quotesExist, setQuotesExist] = useState(quotesCount > 0)
  const [rowCount, setRowCount] = useState<number | null>(null)
  const handleRowCountChanged = useCallback((next: number | null) => {
    setRowCount(next)
  }, [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // A successful save (create or edit) refreshes the grid and — for a
  // create fired from the empty state — reveals it for the first time
  // (AC-054). Re-marking an already-true `quotesExist` on every edit save is
  // a no-op, not a parallel counter: the DISPLAYED count still comes only
  // from `rowCount`/`quotesCount` below.
  const handleSaved = useCallback(() => {
    refreshGrid()
    setQuotesExist(true)
  }, [refreshGrid])

  // D-3: create/view/edit always open in a Sheet above the Opportunity,
  // regardless of the actor's own open-mode preference.
  const { openCreateWith, openView, openEdit, sheet } = useModuleOpener(QUOTES_DOMAIN, {
    onSaved: handleSaved,
    forceMode: OPEN_MODE_MODAL,
  })

  const handleCreate = useCallback(
    () => openCreateWith({ opportunity_id: opportunityId }),
    [openCreateWith, opportunityId],
  )

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteQuote(row.id)
        toast.success(t('quotes.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(status === 403 ? t('quotes.form.deleteForbidden') : t('quotes.form.deleteError'))
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'edit':
          openEdit(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [openView, openEdit, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const closeActivity = useCallback((open: boolean) => {
    if (!open) {
      setActivityRow(null)
    }
  }, [])

  return {
    tableRef,
    count: rowCount ?? quotesCount,
    showEmptyState: !quotesExist,
    isBusy,
    handleAction,
    handleRowCountChanged,
    handleCreate,
    activityRow,
    closeActivity,
    sheet,
  }
}
