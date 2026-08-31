import type { ActionType } from '@/features/table/types'

/**
 * The ONE place an action's `type` becomes a colour, so a domain action looks
 * the same wherever it is offered — as an icon button in the grid's actions
 * column, or as a labelled button in a record's actions bar (user directive
 * 2026-08-31: "tutti i bottoni omogenei, un colore per ogni azione positiva e
 * negativa").
 *
 * The classification itself is NOT decided here: it is the `type` the server's
 * action catalog assigns to each key (`*ColumnCatalog::actions()`), so grid and
 * detail read the same verdict instead of each hard-coding its own.
 */

/** `Button` variant for an action rendered with a visible label (record actions bar). */
export const ACTION_BUTTON_VARIANT = {
  // Neutral: navigation and plain edits carry no outcome, so they stay the
  // app's non-blending outline (`frontend.md` §9 — never a bare transparent
  // button on a light body).
  link: 'outline',
  action: 'outline',
  success: 'success',
  danger: 'destructive',
} as const satisfies Record<ActionType, string>

/** Extra classes for an action rendered as a bare icon button (grid actions column). */
export const ACTION_ICON_CLASS: Record<ActionType, string | undefined> = {
  link: undefined,
  action: undefined,
  success: 'text-success hover:text-success',
  danger: 'text-destructive hover:text-destructive',
}

/** `DropdownMenuItem` variant for an action folded into the grid's overflow menu. */
export function actionMenuVariant(type: ActionType): 'default' | 'destructive' {
  return type === 'danger' ? 'destructive' : 'default'
}
