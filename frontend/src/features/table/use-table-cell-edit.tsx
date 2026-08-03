import { useCallback, useState } from 'react'
import type { ReactNode } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import axios from 'axios'
import { toast } from 'sonner'
import type { CellValueChangedEvent, IRowNode } from 'ag-grid-community'
import type { ApiErrorResponse } from '@/api/types'
import { CellNoteDialog } from '@/components/data-table/cell-note-dialog'
import { updateTableCell } from '@/features/table/api'
import type { TableColumn, TableRow } from '@/features/table/types'

/** One {funzione aziendale, categoria prodotto} pair as a `product_lines` cell PATCH sends it (spec 0075). */
type CellPatchPair = Record<string, number>

/**
 * A cell PATCH's value: a scalar, or — for a `multiselect` column — the whole
 * id collection, or — for a `product_lines` column (spec 0075) — the whole
 * collection of id pairs.
 */
type CellPatchValue = string | number | boolean | null | number[] | CellPatchPair[]

/** Body of a single cell PATCH, already resolved to the wire shape (spec 0053/0054). */
interface CellPatchArgs {
  rowId: number
  column: string
  value: CellPatchValue
  note?: string
}

/** Resolves the toast message for a failed cell PATCH: the server's own message (D-9), a generic fallback otherwise. */
function resolveCellUpdateErrorMessage(error: unknown, t: TFunction): string {
  if (axios.isAxiosError<ApiErrorResponse>(error) && error.response?.data?.message) {
    return error.response.data.message
  }
  return t('table.cellUpdateError')
}

/**
 * An entry of an ID-PAIR collection (spec 0075, `product_lines`): only its
 * `*_id` keys travel — the sibling `*_name` keys are there to label the cell
 * and the editor's chips, and have no place in the payload.
 */
function resolvePairEntry(entry: Record<string, unknown>): CellPatchPair {
  const pair: CellPatchPair = {}

  for (const [key, cell] of Object.entries(entry)) {
    if (key.endsWith('_id') && typeof cell === 'number') {
      pair[key] = cell
    }
  }

  return pair
}

/**
 * A relation column's cell value is the related row's `{id, name}`
 * projection (backend `mapRow`), not the id the PATCH contract expects
 * (spec 0054 D-3): unwrap it here, once, so every other column's plain
 * scalar value passes through untouched. A MULTISELECT column's value (user
 * directive 2026-07-23) is the ARRAY of those projections and unwraps the same
 * way, element by element, into the id collection the endpoint replaces. An
 * entry with no `id` of its own is an ID PAIR (spec 0075): it keeps its shape,
 * reduced to the `*_id` keys the endpoint reads.
 */
function resolveCellPatchValue(value: unknown): CellPatchValue {
  if (Array.isArray(value)) {
    if (value.every((entry) => entry !== null && typeof entry === 'object' && !('id' in entry))) {
      return value.map((entry) => resolvePairEntry(entry as Record<string, unknown>))
    }

    return value.map((entry) =>
      entry !== null && typeof entry === 'object' && 'id' in entry ? (entry as { id: number }).id : Number(entry),
    )
  }
  if (value !== null && typeof value === 'object' && 'id' in value) {
    return (value as { id: number }).id
  }
  return value as CellPatchValue
}

/**
 * Whether a commit actually changed anything. Identity is enough for a scalar
 * (and for the object-valued editors, which deliberately re-emit the SAME
 * reference on a no-op pick), but never for a multiselect: its editor rebuilds
 * the array on every toggle, so two equal selections are always different
 * references — compared by their id sets instead.
 */
function isUnchangedCellValue(newValue: unknown, oldValue: unknown): boolean {
  if (Array.isArray(newValue) && Array.isArray(oldValue)) {
    // Compared on the WIRE shape, so an id collection and a pair collection
    // (spec 0075) are both covered: `resolveCellPatchValue` emits their keys
    // in a fixed order, which makes the serialization comparable.
    return JSON.stringify(resolveCellPatchValue(newValue)) === JSON.stringify(resolveCellPatchValue(oldValue))
  }

  return newValue === oldValue
}

/**
 * Whether the column's newly-picked value requires an accompanying note
 * (spec 0054 D-5): driven entirely by the column's own metadata, never by
 * column id.
 *
 * The flag is read from the backend-resolved `options` of an `editor:
 * 'select'` column (spec 0055 D-5 — where request-management's working
 * statuses actually carry it) and, as a fallback, from a badge column's
 * `badges`. The value compared is the PATCH value (an id for a select/relation
 * column, the scalar itself otherwise), so both shapes match on the same
 * comparison. The rule is enforced server-side regardless: this only lets the
 * grid ask before committing instead of surfacing a 422 afterwards.
 */
function resolveRequiresNote(columns: TableColumn[], columnId: string, patchValue: unknown): boolean {
  const column = columns.find((candidate) => candidate.id === columnId)

  if (column === undefined) {
    return false
  }

  const optionRequiresNote = (column.options ?? []).some(
    (option) =>
      typeof option === 'object' &&
      option !== null &&
      String(option.value) === String(patchValue) &&
      option.requires_note === true,
  )

  return (
    optionRequiresNote ||
    (column.badges?.some((badge) => badge.value === patchValue && badge.requires_note === true) ?? false)
  )
}

/** A cell edit awaiting its note before it can PATCH (spec 0054 D-5). */
interface PendingNoteEdit {
  event: CellValueChangedEvent<TableRow>
  patchValue: CellPatchValue
  revertedData: TableRow
}

/**
 * Wires AG Grid's `onCellValueChanged` to the generic per-cell PATCH endpoint
 * (spec 0053, extended by 0054 D-5): guards a no-op edit, swaps the row for
 * the server's re-mapped copy on success (`node.setData`), and reverts to the
 * previous value with a toast of the server's message on failure. Mirrors the
 * import wizard's review grid (`features/imports/wizard/use-review-rows.ts`),
 * the only prior cell-edit -> PATCH -> setData/revert cycle in the repo —
 * that engine stays untouched, this is the generic table's own instance of
 * the same pattern.
 *
 * `columns` (the domain's resolved config) is read only to look up whether
 * the newly-picked value `requires_note`; when it does, the PATCH is held
 * back until the returned `noteDialogSlot` collects one (confirm -> single
 * PATCH with `{column, value, note}`; cancel -> local revert, no request).
 */
export function useTableCellEdit(domain: string, columns: TableColumn[]) {
  const { t } = useTranslation()
  const [pendingNote, setPendingNote] = useState<PendingNoteEdit | null>(null)

  // Only `mutate` is read, and it is referentially stable across renders —
  // unlike the mutation object itself. `runPatch` below ends up (via
  // `handleCellValueChanged`) in `DataTable`'s `gridOptions` memo, and an
  // unstable identity there rebuilds `defaultColDef` on every render, which
  // makes AG Grid re-apply the column definitions and drop the user's manual
  // column width/order.
  const { mutate: patchCell } = useMutation({
    mutationFn: ({ rowId, column, value, note }: CellPatchArgs) =>
      updateTableCell(domain, rowId, { column, value, ...(note !== undefined ? { note } : {}) }),
  })

  const runPatch = useCallback(
    (args: CellPatchArgs, node: IRowNode<TableRow>, revertedData: TableRow) => {
      patchCell(args, {
        onSuccess: (row) => {
          node.setData(row)
        },
        onError: (error) => {
          node.setData(revertedData)
          toast.error(resolveCellUpdateErrorMessage(error, t))
        },
      })
    },
    [t, patchCell],
  )

  // Step 1: ignore edits with no side effect — an unauthorized/unregistered
  // column never wires an editor at all, and a same-value commit (Esc, or
  // Enter without a change) must not fire a network call (AC-021 / 0054
  // AC-018's "annulla" case for a relation pick, which never touches this
  // path at all since a cancelled relation editor never calls onValueChange).
  // Step 2: a value that requires a note holds the PATCH until the dialog
  // resolves it (D-5); everything else PATCHes immediately, unchanged from
  // 0053.
  const handleCellValueChanged = useCallback(
    (event: CellValueChangedEvent<TableRow>) => {
      if (!event.data || isUnchangedCellValue(event.newValue, event.oldValue)) {
        return
      }

      const rowId = event.data.id
      const columnId = event.column.getColId()
      const revertedData: TableRow = { ...event.data, [columnId]: event.oldValue }
      const patchValue = resolveCellPatchValue(event.newValue)

      if (resolveRequiresNote(columns, columnId, patchValue)) {
        setPendingNote({ event, patchValue, revertedData })
        return
      }

      runPatch({ rowId, column: columnId, value: patchValue }, event.node, revertedData)
    },
    [columns, runPatch],
  )

  const handleConfirmNote = useCallback(
    (note: string) => {
      if (!pendingNote) {
        return
      }
      const { event, patchValue, revertedData } = pendingNote
      setPendingNote(null)
      runPatch(
        { rowId: event.data.id, column: event.column.getColId(), value: patchValue, note },
        event.node,
        revertedData,
      )
    },
    [pendingNote, runPatch],
  )

  const handleCancelNote = useCallback(() => {
    if (!pendingNote) {
      return
    }
    pendingNote.event.node.setData(pendingNote.revertedData)
    setPendingNote(null)
  }, [pendingNote])

  const noteDialogSlot: ReactNode = pendingNote ? (
    <CellNoteDialog onConfirm={handleConfirmNote} onCancel={handleCancelNote} />
  ) : null

  return { handleCellValueChanged, noteDialogSlot }
}
