import { useMemo } from 'react'
import { SCALAR_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/scalar-field-component-registry'
import { toCell, toColumnDescriptor } from '@/features/custom-fields/table-field-model'
import type { TableFieldCell, TableFieldColumn } from '@/features/custom-fields/types'

interface TableFieldCellProps {
  column: TableFieldColumn
  value: TableFieldCell | undefined
  onChange: (cell: TableFieldCell) => void
  disabled: boolean
  readOnly: boolean
  /** DOM id of the input; the error element is `${id}-error`. */
  id: string
  error: string | undefined
  /** Mobile cards show the column label; the desktop table leaves it to the header (screen-reader only here). */
  showLabel: boolean
}

/**
 * One cell: the column rendered by the scalar control for its type, with the
 * accessible-error triad (`aria-invalid` + `aria-describedby` + `role="alert"`).
 */
export function TableFieldCell({ column, value, onChange, disabled, readOnly, id, error, showLabel }: TableFieldCellProps) {
  const descriptor = useMemo(() => toColumnDescriptor(column), [column])
  const Control = SCALAR_FIELD_COMPONENT_REGISTRY[column.type]
  const errorId = `${id}-error`

  return (
    <div className="flex min-w-28 flex-col gap-1">
      <label htmlFor={id} className={showLabel ? 'text-xs font-medium text-muted-foreground' : 'sr-only'}>
        {column.label}
        {showLabel && column.required ? <span className="text-destructive"> *</span> : null}
      </label>
      <Control
        descriptor={descriptor}
        value={value ?? null}
        onChange={(next) => onChange(toCell(next))}
        disabled={disabled}
        readOnly={readOnly}
        id={id}
        describedBy={error ? errorId : ''}
        invalid={Boolean(error)}
      />
      {error ? (
        <span id={errorId} role="alert" className="text-xs text-destructive">
          {error}
        </span>
      ) : null}
    </div>
  )
}
