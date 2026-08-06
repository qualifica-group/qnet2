import { useCallback, useRef, useState, type ReactNode } from 'react'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { useQuoteRowActions, type QuoteNotesTarget } from '@/features/quotes/use-quote-row-actions'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRow } from '@/features/table/types'
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
  /** The Offerta whose notes are open, `null` when the dialog is closed. */
  notesTarget: QuoteNotesTarget | null
  closeNotes: (open: boolean) => void
  /** Ricarica la griglia: la passa il pannello a `onThreadChanged` del dialog note. */
  refreshRows: () => void
  sheet: ReactNode
}

/**
 * Orchestrates the Opportunity detail's Quotes panel (spec 0067): the
 * empty-state/counter derivation (D-9) and the create affordance, on top of
 * `useQuoteRowActions` — lo STESSO comportamento delle azioni di riga che
 * usano la griglia Offerte e il pannello espanso nella riga Opportunita'
 * (direttiva utente 2026-08-06). Questa superficie aveva una copia parziale
 * dello switch (niente `notes`, niente `generate_document`): due azioni del
 * catalogo restavano affordance morte.
 */
export function useOpportunityQuotesPanel(
  opportunityId: number,
  quotesCount: number,
): UseOpportunityQuotesPanelResult {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [quotesExist, setQuotesExist] = useState(quotesCount > 0)
  const [rowCount, setRowCount] = useState<number | null>(null)
  const handleRowCountChanged = useCallback((next: number | null) => {
    setRowCount(next)
  }, [])

  // Ogni mutazione (save o delete) ricarica la griglia; un save partito
  // dall'empty state la rivela per la prima volta (AC-054). Ri-marcare un
  // `quotesExist` gia' true e' un no-op, non un contatore parallelo: il numero
  // MOSTRATO viene solo da `rowCount`/`quotesCount`.
  const handleMutated = useCallback(() => {
    refreshGrid()
    setQuotesExist(true)
  }, [refreshGrid])

  // D-3: create/view/edit always open in a Sheet above the Opportunity,
  // regardless of the actor's own open-mode preference.
  const {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    notesTarget,
    closeNotes,
    openCreateWith,
    sheet,
  } = useQuoteRowActions({ onMutated: handleMutated, forceMode: OPEN_MODE_MODAL })

  const handleCreate = useCallback(
    () => openCreateWith({ opportunity_id: opportunityId }),
    [openCreateWith, opportunityId],
  )

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
    notesTarget,
    closeNotes,
    refreshRows: refreshGrid,
    sheet,
  }
}
