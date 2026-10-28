/**
 * Column templates of the product's suppliers list (spec 0320), shared by the
 * read-only table and the editor so price, cost and margin line up on both:
 * supplier · price · cost · margin (· default · remove in the editor). Full
 * literal class strings: Tailwind only generates classes it can read verbatim.
 */
export const SUPPLIER_TABLE_GRID = 'grid grid-cols-[minmax(0,1fr)_6.5rem_6.5rem_7.5rem] items-center gap-3'

/** A read-only row: stacked below the `@xl` container (576px), on the table's columns from it up. */
export const SUPPLIER_TABLE_ROW_GRID = 'flex flex-col gap-2 @xl:grid @xl:grid-cols-[minmax(0,1fr)_6.5rem_6.5rem_7.5rem] @xl:items-center @xl:gap-3'

/** The editor's header row, rendered inside a `hidden @3xl:block` wrapper. */
export const SUPPLIER_EDITOR_HEADER_GRID = 'grid grid-cols-[minmax(0,1fr)_6.5rem_6.5rem_7.5rem_4.5rem_2rem] items-center gap-3'

/** An editor row: stacked below the `@3xl` container (768px, the picker needs room), on the header's columns from it up. */
export const SUPPLIER_EDITOR_ROW_GRID = 'flex flex-col gap-2 @3xl:grid @3xl:grid-cols-[minmax(0,1fr)_6.5rem_6.5rem_7.5rem_4.5rem_2rem] @3xl:items-start @3xl:gap-3'

/** Labels a value only while the row is stacked: from the breakpoint up the header row carries the column names. */
export const SUPPLIER_MOBILE_LABEL = {
  table: 'text-[11px] text-muted-foreground @xl:hidden',
  editor: 'text-[11px] text-muted-foreground @3xl:hidden',
} as const
