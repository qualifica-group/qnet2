/**
 * Layout primitives of the read-only RECORD screens (the enterprise-CRM
 * detail: Opportunita', Prodotti). Neutral home so the screens share the
 * LITERAL same classes instead of copies free to drift apart — the same rule
 * `components/record-form/layout.ts` applies to the forms.
 */

/**
 * Body grid of a record canvas: the record itself first, an optional side
 * column after it, stacked in that order while narrow (user directive
 * 2026-08-05).
 */
export const RECORD_BODY_GRID_CLASS = 'grid grid-cols-1 items-start gap-4'

/**
 * Added only when a side column is actually laid out: with nothing to put in
 * it the record keeps the full width instead of leaving a third of the canvas
 * empty.
 */
export const RECORD_BODY_WITH_SIDE_CLASS = '@5xl:grid-cols-[minmax(0,7fr)_minmax(0,4fr)]'

/**
 * Each column is its OWN `@container`, so the section/field grids inside break
 * on the COLUMN's width, not the canvas' — without this the main card would
 * still go two-column at the exact width where it just lost a third of its
 * space to the side column.
 */
export const RECORD_COLUMN_CLASS = '@container flex min-w-0 flex-col gap-4'
