import { TableFieldCell } from '@/features/custom-fields/components/table-field-cell'
import { TableFieldRowRemove } from '@/features/custom-fields/components/table-field-row-remove'
import { TableFieldRowSelect } from '@/features/custom-fields/components/table-field-row-select'
import type { TableFieldViewProps } from '@/features/custom-fields/components/table-field-view-props'
import { isRowSelected } from '@/features/custom-fields/table-field-model'

const HEADER_CELL = 'px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'

/** Desktop: compact table; horizontal overflow stays inside the field container. */
export function TableFieldDesktopView({
  idPrefix,
  config,
  rows,
  disabled,
  readOnly,
  canRemove,
  cellError,
  onCellChange,
  onSelect,
  onRemove,
}: TableFieldViewProps) {
  return (
    <div className="relative w-full contain-inline-size overflow-x-auto rounded-lg border border-border">
      <table className="w-full text-sm">
        <thead className="bg-surface">
          <tr>
            {config.selectable ? <th scope="col" className={HEADER_CELL}>{config.selectable.label}</th> : null}
            {config.columns.map((column) => (
              <th key={column.key} scope="col" className={HEADER_CELL}>
                {column.label}
                {column.required ? <span className="text-destructive"> *</span> : null}
              </th>
            ))}
            {canRemove ? <th scope="col" className="w-8" /> : null}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={row.id ?? `new-${index}`} className="border-t border-border">
              {config.selectable ? (
                <td className="px-2 py-1 align-top">
                  <TableFieldRowSelect
                    groupName={`${idPrefix}-selected`}
                    index={index}
                    checked={isRowSelected(row, config)}
                    disabled={disabled || readOnly}
                    onSelect={() => onSelect(index)}
                  />
                </td>
              ) : null}
              {config.columns.map((column) => (
                <td key={column.key} className="px-2 py-1 align-top">
                  <TableFieldCell
                    column={column}
                    value={row[column.key]}
                    onChange={(cell) => onCellChange(index, column.key, cell)}
                    disabled={disabled}
                    readOnly={readOnly}
                    id={`${idPrefix}-${index}-${column.key}`}
                    error={cellError(index, column.key)}
                    showLabel={false}
                  />
                </td>
              ))}
              {canRemove ? (
                <td className="px-2 py-1 align-top">
                  <TableFieldRowRemove index={index} onRemove={() => onRemove(index)} />
                </td>
              ) : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
