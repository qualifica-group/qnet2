/**
 * Readers for a column's `options`, which carry TWO shapes since spec 0055
 * D-2: plain scalars for enum/badge/tags columns (the set-filter and rich
 * editor catalogue) and `SelectOption` objects for an `editor: 'select'`
 * column, whose `value` is the id the PATCH submits.
 *
 * Every consumer narrows through one of these two functions instead of
 * asserting a shape: the wrong shape yields an empty list, never a
 * half-rendered option row. The one exception is an `attr.<code>` enum
 * column, whose catalogue arrives as `{value, label}` objects (spec 0064
 * contract): its scalar list is those objects' `value`s.
 */
import type { SelectOption, TableColumn } from '@/features/table/types'

/** The SCALAR option list of an enum/badge/tags column. */
export function scalarColumnOptions(column: TableColumn | undefined): string[] {
  return (column?.options ?? []).map((option) =>
    typeof option === 'object' && option !== null ? String(option.value) : option,
  )
}

/** The OBJECT option list of an `editor: 'select'` column. */
export function selectColumnOptions(column: TableColumn | undefined): SelectOption[] {
  return (column?.options ?? []).filter(
    (option): option is SelectOption => typeof option === 'object' && option !== null,
  )
}
