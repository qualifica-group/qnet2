/**
 * Layout primitives of the record forms — Gestione Richieste (work panel and
 * create form) and Opportunità. Neutral home so the screens share the LITERAL
 * same classes instead of copies free to drift apart (user directive
 * 2026-08-05: "voglio che siano uguali di posizione e di design").
 */

/**
 * Sticky identity bar: same height, same paddings, same backdrop on every
 * record form, so the primary action stays reachable while the long column
 * below scrolls.
 */
export const RECORD_HEADER_CLASS =
  'sticky top-0 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b bg-card/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-card/80'

/**
 * The body split. These screens are mounted both in a dedicated page and in a
 * Sheet, so the two columns must react to the CONTAINER width, not the
 * viewport: `@container` + `@4xl:` (56rem) instead of `lg:`/`xl:`. Below that
 * width everything collapses to a single column.
 */
export const PANEL_GRID_CLASS = 'grid items-start gap-4 p-4 @4xl:grid-cols-[minmax(0,1fr)_20rem]'

/** Clears the sticky header (`py-3` around a badge row) so the side column never scrolls under it. */
export const SIDE_COLUMN_CLASS = 'flex min-w-0 flex-col gap-4 @4xl:sticky @4xl:top-16 @4xl:order-2'

/** The main column: its own `@container`, so the sections split on ITS width, not the panel's. */
export const MAIN_COLUMN_CLASS = '@container flex min-w-0 flex-col gap-4 @4xl:order-1'

/**
 * The grid every section lays its pickers on: one column on a narrow panel,
 * two from `@2xl`. `items-start` keeps a cell whose field carries an appendix
 * (the reward inset, a contacts recap, a scoping hint) from stretching its
 * neighbour.
 */
export const FIELD_GRID_CLASS = 'grid min-w-0 items-start gap-4 @2xl:grid-cols-2'

/**
 * A picker plus whatever hangs under it. `gap-2` is `FormItem`'s own
 * label-to-control gap, so an appendix sits on the same vertical rhythm as the
 * field it belongs to instead of floating.
 */
export const FIELD_STACK_CLASS = 'flex min-w-0 flex-col gap-2'

/**
 * The "Note generali" callout chrome. Amber, not the brand hue (user directive
 * 2026-07-29): the whole surface scale is blue-grey, so a `primary` wash on
 * `bg-surface` separates by lightness only and reads as the same plane. A warm
 * hue separates by chroma instead, which survives both themes.
 */
export const GENERAL_NOTES_CALLOUT_CLASS =
  'rounded-lg border border-amber-500/40 border-l-4 border-l-amber-500 bg-amber-100 p-3 shadow-sm dark:border-amber-400/30 dark:border-l-amber-400 dark:bg-amber-400/15'

export const GENERAL_NOTES_TITLE_CLASS =
  'flex items-center gap-1.5 text-xs font-semibold tracking-tight text-amber-800 uppercase dark:text-amber-300'
