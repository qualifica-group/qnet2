import { useEffect } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useOpenOfferLines } from '@/features/request-management/offer-lines-dialog-context'
import type { TableRow } from '@/features/table/types'

/**
 * The `editor: 'offer_lines'` cell editor (user directive 2026-09-07:
 * "l'edit della cella deve essere lo stesso flusso delle altre colonne").
 *
 * It renders nothing of its own: it hands the row to the dialog
 * `OfferLinesDialogProvider` mounts once for the grid, then closes itself. So
 * the GESTURE stays the grid's (single click, or Enter on the focused cell,
 * gated by the row's own `editable` flag — identical to every other editable
 * column) while the UI is a dialog.
 *
 * A dialog, and not an in-cell popup, because an offer row is composed with
 * `AsyncPaginatedSelect` (product and VAT rate) whose Radix popup portals to
 * `document.body`: inside a cell editor `stopEditingWhenCellsLoseFocus` then
 * tears the editor down mid-pick — the very reason `ProductLinesCellEditor`
 * and `MultiSelectCellEditor` hand-roll in-popup lists. Those pickers do work
 * inside a dialog, which is what lets this reuse the Offerte row editor
 * verbatim instead of cloning it.
 *
 * For the same reason the editor cancels IMMEDIATELY (`stopEditing(true)`)
 * instead of waiting for the dialog: focus leaves the grid the moment the
 * dialog opens, so AG Grid would tear this component down anyway — better
 * deliberately, before anything depends on it being alive. The commit is the
 * dialog's, on the SAME `PATCH /tables/{domain}/rows/{row}` every other cell
 * goes through, replacing this very row on success.
 */
export function OfferLinesCellEditor({ data, node, stopEditing }: CustomCellEditorProps<TableRow>) {
  const { openOfferLines } = useOpenOfferLines()

  useEffect(() => {
    const quoteId = typeof data?.id === 'number' ? data.id : null

    if (quoteId !== null) {
      openOfferLines({ quoteId, node })
    }

    // `true` suppresses the post-edit navigation: the operator's focus is
    // going into the dialog, not onto the next cell.
    stopEditing(true)
  }, [data, node, openOfferLines, stopEditing])

  return null
}
