import { createContext, useContext } from 'react'

interface OfferLinesDialogContextValue {
  /**
   * Imperative entry point the grid cell calls to open the offer-rows quick
   * edit on one request (user directive 2026-09-07). Takes the Offerta id —
   * the row's own `id` since spec 0086.
   */
  openOfferLines: (quoteId: number) => void
}

/** No-op default: a cell rendered outside the provider degrades to "nothing happens" instead of throwing. */
const NOOP_CONTEXT_VALUE: OfferLinesDialogContextValue = {
  openOfferLines: () => {},
}

/**
 * The opener context, split from the provider on purpose (mirrors
 * `use-field-change-request-dialog.ts`): a cell renderer only depends on this
 * tiny hook, never on the dialog's own query/form/mutation graph.
 */
export const OfferLinesDialogContext =
  createContext<OfferLinesDialogContextValue>(NOOP_CONTEXT_VALUE)

/** Access the shared offer-rows quick-edit opener from any grid cell. */
export function useOpenOfferLines(): OfferLinesDialogContextValue {
  return useContext(OfferLinesDialogContext)
}
