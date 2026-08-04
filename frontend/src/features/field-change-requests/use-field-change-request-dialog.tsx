import { createContext, useContext } from 'react'
import type { RequestFieldChangeParams } from '@/features/field-change-requests/types'

interface FieldChangeRequestDialogContextValue {
  /**
   * Imperative entry point any write-interception channel (panel field, grid
   * cell — microtasks E4/E5) calls to open the generic proposal dialog
   * instead of writing the field directly (D-2).
   */
  requestFieldChange: (params: RequestFieldChangeParams) => void
}

/** No-op default: a caller rendered outside the provider degrades to "nothing happens" instead of throwing. */
const NOOP_CONTEXT_VALUE: FieldChangeRequestDialogContextValue = {
  requestFieldChange: () => {},
}

/**
 * The opener context, split from the provider component on purpose (mirrors
 * `user-detail-sheet-context.ts`): a write-interception point only depends on
 * this tiny hook, not on the dialog's own form/mutation graph.
 */
export const FieldChangeRequestDialogContext =
  createContext<FieldChangeRequestDialogContextValue>(NOOP_CONTEXT_VALUE)

/** Access the shared field-change-request opener from any write-interception point. */
export function useRequestFieldChange(): FieldChangeRequestDialogContextValue {
  return useContext(FieldChangeRequestDialogContext)
}
