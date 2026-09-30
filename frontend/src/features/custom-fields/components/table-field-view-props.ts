import type { TableFieldCell, TableFieldConfig, TableFieldRow } from '@/features/custom-fields/types'

/** Contract shared by the desktop table and the mobile card list of `TableFieldControl`. */
export interface TableFieldViewProps {
  /** Unique per field instance: prefix of every cell id and the radio group name. */
  idPrefix: string
  config: TableFieldConfig
  rows: TableFieldRow[]
  disabled: boolean
  readOnly: boolean
  /** Removal is offered only while editable. */
  canRemove: boolean
  cellError: (index: number, columnKey: string) => string | undefined
  onCellChange: (index: number, columnKey: string, cell: TableFieldCell) => void
  onSelect: (index: number) => void
  onRemove: (index: number) => void
}
