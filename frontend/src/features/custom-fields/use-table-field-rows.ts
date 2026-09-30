import { useMemo } from 'react'
import {
  TABLE_DEFAULT_MAX_ROWS,
  createEmptyRow,
  readRows,
  selectRow,
  setCell,
} from '@/features/custom-fields/table-field-model'
import type { CustomFieldValue, TableFieldCell, TableFieldConfig } from '@/features/custom-fields/types'

/**
 * Row operations of a table field over a controlled value: every handler emits
 * a fresh `{ rows }` (never `summary`, which the server computes) and keeps the
 * `id` of untouched rows so the server preserves them.
 */
export function useTableFieldRows(
  config: TableFieldConfig,
  value: CustomFieldValue,
  onChange: (value: CustomFieldValue) => void,
) {
  const rows = useMemo(() => readRows(value), [value])
  const maxRows = config.max_rows ?? TABLE_DEFAULT_MAX_ROWS

  return {
    rows,
    canAddRow: rows.length < maxRows,
    addRow: () => onChange({ rows: [...rows, createEmptyRow(config)] }),
    removeRow: (index: number) => onChange({ rows: rows.filter((_, rowIndex) => rowIndex !== index) }),
    changeCell: (index: number, key: string, cell: TableFieldCell) =>
      onChange({ rows: setCell(rows, index, key, cell) }),
    selectRow: (index: number) => onChange({ rows: selectRow(rows, config, index) }),
  }
}
