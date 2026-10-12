/**
 * Shared grid of the RDA line (spec 0209 D-9): the header and every row use the
 * SAME column template so the single header above the rows lines up. From `lg`
 * a line is one tabular band (product, description, supplier, U.m., quantity,
 * price, VAT, total, status, ODA, actions); below it the fields wrap as a
 * compact card.
 */
const DESKTOP_COLUMNS =
  'lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.6fr)_minmax(0,1.2fr)_4.5rem_4rem_5.5rem_5.5rem_5rem_6rem_2.5rem_8.5rem]'

export const LINE_GRID = `grid grid-cols-2 gap-1.5 sm:grid-cols-4 ${DESKTOP_COLUMNS} lg:items-center`

export const LINE_HEADER_GRID = `hidden gap-1.5 px-2 text-[11px] font-medium text-muted-foreground lg:grid ${DESKTOP_COLUMNS}`

/** A wide field: its own row on phones and small screens, one column in the desktop band. */
export const WIDE_CELL = 'col-span-2 sm:col-span-4 lg:col-span-1'

/** A narrow cell that keeps its caption on phones only (`data-label`), where there is no column header. */
export const CAPTIONED_CELL =
  'text-xs tabular-nums before:mr-1 before:text-[11px] before:text-muted-foreground before:content-[attr(data-label)] lg:before:hidden'
