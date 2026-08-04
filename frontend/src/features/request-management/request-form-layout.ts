/**
 * The grid every Gestione Richieste form section lays its pickers on: one
 * column on a narrow panel, two from `@2xl`. `items-start` keeps a cell whose
 * field carries an appendix (the reward inset, a scoping hint) from stretching
 * its neighbour.
 */
export const FIELD_GRID_CLASS = 'grid min-w-0 items-start gap-4 @2xl:grid-cols-2'

/**
 * A picker plus whatever hangs under it. `gap-2` is `FormItem`'s own
 * label-to-control gap, so an appendix sits on the same vertical rhythm as the
 * field it belongs to instead of floating.
 */
export const FIELD_STACK_CLASS = 'flex min-w-0 flex-col gap-2'
