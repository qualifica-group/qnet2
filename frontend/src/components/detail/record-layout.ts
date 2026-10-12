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
 * Create forms (`RecordFormBody`): added only when a side column is actually
 * laid out; with nothing to put in it the form keeps the full width.
 */
export const RECORD_BODY_WITH_SIDE_CLASS = '@5xl:grid-cols-[minmax(0,7fr)_minmax(0,4fr)]'

/**
 * The same 7:4 split, unconditional: used when the layout is chosen by
 * measuring (`useRecordSideLayout`), not by a container query.
 */
export const RECORD_SIDE_COLUMNS_CLASS = 'grid-cols-[minmax(0,7fr)_minmax(0,4fr)]'

/**
 * Side column collapsed to its icon rail (an `icon-sm` button + the card's
 * padding and border). Spelled with the SAME track shapes as the 7:4 split —
 * `minmax(<length>, <flex>)` — so the open/close transition interpolates
 * between the two: the 0fr track takes no free space and stays at its 2.75rem
 * minimum.
 */
export const RECORD_RAIL_COLUMNS_CLASS = 'grid-cols-[minmax(0,7fr)_minmax(2.75rem,0fr)]'

/**
 * Narrowest body that still lays the side column out: mirrors `@5xl` (64rem)
 * on the canvas minus its `p-4` padding, the threshold of the 7:4 split.
 */
export const RECORD_SIDE_LAYOUT_MIN_WIDTH_PX = 992

/**
 * Each column is its OWN `@container`, so the section/field grids inside break
 * on the COLUMN's width, not the canvas' — without this the main card would
 * still go two-column at the exact width where it just lost a third of its
 * space to the side column.
 */
export const RECORD_COLUMN_CLASS = '@container flex min-w-0 flex-col gap-4'
