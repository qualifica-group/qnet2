import type { TFunction } from 'i18next'
import { formatDateTimeOptionalTime } from '@/lib/formatting/date-display'
import type {
  TableFieldCell,
  TableFieldColumn,
  TableFieldConfig,
  TableFieldRow,
} from '@/features/custom-fields/types'

const INLINE_SEPARATOR = ' · '

/** A cell as display text per its column type; blank when unset. */
export function formatTableCell(
  column: TableFieldColumn,
  cell: TableFieldCell | undefined,
  t: TFunction,
): string {
  if (cell === null || cell === undefined || cell === '') {
    return ''
  }
  switch (column.type) {
    case 'boolean':
      return cell ? t('customFields.tableField.yes') : t('customFields.tableField.no')
    case 'enum':
      return column.options?.find((option) => option.value === String(cell))?.label ?? String(cell)
    case 'date':
    case 'datetime':
      return formatDateTimeOptionalTime(cell) || String(cell)
    default:
      return String(cell)
  }
}

/** The row flagged by the `selectable` column, if the definition has one. */
export function findSelectedRow(config: TableFieldConfig, rows: TableFieldRow[]): TableFieldRow | undefined {
  const key = config.selectable?.key
  return key ? rows.find((row) => row[key] === true) : undefined
}

/**
 * One row on a single line: the non-empty column values joined by " · ";
 * booleans carry their column label ("Certified: Yes") since a bare Yes/No
 * would be meaningless inline.
 */
export function formatTableRowInline(config: TableFieldConfig, row: TableFieldRow, t: TFunction): string {
  return config.columns
    .map((column) => {
      const text = formatTableCell(column, row[column.key], t)
      return text !== '' && column.type === 'boolean' ? `${column.label}: ${text}` : text
    })
    .filter((text) => text !== '')
    .join(INLINE_SEPARATOR)
}
