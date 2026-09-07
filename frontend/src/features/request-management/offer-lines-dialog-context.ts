import { createContext, useContext } from 'react'
import type { IRowNode } from 'ag-grid-community'
import type { TableRow } from '@/features/table/types'

/** What the cell editor hands over when it delegates its edit to the dialog. */
export interface OfferLinesEditTarget {
  /** The Offerta id — the grid row's own `id` since spec 0086. */
  quoteId: number
  /** The row the commit replaces on success, exactly as `useTableCellEdit` does for every other cell. */
  node: IRowNode<TableRow>
}

interface OfferLinesDialogContextValue {
  openOfferLines: (target: OfferLinesEditTarget) => void
}

/** No-op default: an editor rendered outside the provider degrades to "nothing happens" instead of throwing. */
const NOOP_CONTEXT_VALUE: OfferLinesDialogContextValue = {
  openOfferLines: () => {},
}

/**
 * The opener context, split from the provider on purpose (mirrors
 * `use-field-change-request-dialog.ts`): the cell editor only depends on this
 * tiny hook, never on the dialog's own query/form/mutation graph.
 */
export const OfferLinesDialogContext =
  createContext<OfferLinesDialogContextValue>(NOOP_CONTEXT_VALUE)

/** Access the shared offer-rows editor from the cell editor that delegates to it. */
export function useOpenOfferLines(): OfferLinesDialogContextValue {
  return useContext(OfferLinesDialogContext)
}
