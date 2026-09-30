import type {
  CustomFieldConfig,
  CustomFieldDescriptor,
  CustomFieldValue,
  TableFieldCell,
  TableFieldColumn,
  TableFieldConfig,
  TableFieldRow,
  TableFieldValue,
} from '@/features/custom-fields/types'

/**
 * Pure helpers behind `TableFieldControl` and the `table` branches of the
 * schema/values modules (spec 0180): everything that reads a table
 * definition or reshapes rows lives here so the components stay presentational.
 */

/** Backend `table.max_rows` default (config/custom-fields.php); an unset `max_rows` means this cap. */
export const TABLE_DEFAULT_MAX_ROWS = 200

const EMPTY_COLUMNS: TableFieldColumn[] = []
const EMPTY_ROWS: TableFieldRow[] = []

/** Table config out of a loose `config` object (descriptor or attribute), tolerating missing/malformed parts. */
export function toTableConfig(config: CustomFieldConfig | null | undefined): TableFieldConfig {
  return {
    columns: config?.columns ?? EMPTY_COLUMNS,
    selectable: config?.selectable ?? null,
    summary: config?.summary ?? null,
    min_rows: config?.min_rows,
    max_rows: config?.max_rows,
  }
}

export function getTableConfig(descriptor: CustomFieldDescriptor): TableFieldConfig {
  return toTableConfig(descriptor.config)
}

export function isTableFieldValue(value: unknown): value is TableFieldValue {
  return typeof value === 'object' && value !== null && !Array.isArray(value) && 'rows' in value
}

/** Rows of a stored value; `null`/malformed values read as no rows. */
export function readRows(value: CustomFieldValue): TableFieldRow[] {
  return isTableFieldValue(value) && Array.isArray(value.rows) ? value.rows : EMPTY_ROWS
}

/** A boolean cell is never "unset": the checkbox has no third state. */
function emptyCell(column: TableFieldColumn): TableFieldCell {
  return column.type === 'boolean' ? false : null
}

/** New row without `id` (the server assigns it), every column unset, nothing selected. */
export function createEmptyRow(config: TableFieldConfig): TableFieldRow {
  const row: TableFieldRow = {}
  for (const column of config.columns) {
    row[column.key] = emptyCell(column)
  }
  if (config.selectable) {
    row[config.selectable.key] = false
  }
  return row
}

/** Whether a row is the selected one (`selectable.key === true`). */
export function isRowSelected(row: Readonly<Record<string, unknown>>, config: TableFieldConfig): boolean {
  return config.selectable ? row[config.selectable.key] === true : false
}

/** Selecting a row clears the flag on every other row; ids and cells are preserved. */
export function selectRow(rows: TableFieldRow[], config: TableFieldConfig, index: number): TableFieldRow[] {
  const key = config.selectable?.key
  if (!key) {
    return rows
  }
  return rows.map((row, rowIndex) => ({ ...row, [key]: rowIndex === index }))
}

export function setCell(
  rows: TableFieldRow[],
  index: number,
  key: string,
  cell: TableFieldCell,
): TableFieldRow[] {
  return rows.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: cell } : row))
}

/** Synthesized descriptor so a column renders through the scalar registry like a standalone field. */
export function toColumnDescriptor(column: TableFieldColumn): CustomFieldDescriptor {
  return {
    key: column.key,
    type: column.type,
    label: column.label,
    source: 'custom',
    group: null,
    mandatory: column.required === true,
    config: column.config ?? null,
    options: column.options,
  }
}

/** Narrows a scalar-control value back to a cell (the scalar controls never emit arrays for column types). */
export function toCell(value: CustomFieldValue): TableFieldCell {
  return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? value : null
}
