import { useTranslation } from 'react-i18next'
import { TableFieldCell } from '@/features/custom-fields/components/table-field-cell'
import { TableFieldRowRemove } from '@/features/custom-fields/components/table-field-row-remove'
import { TableFieldRowSelect } from '@/features/custom-fields/components/table-field-row-select'
import type { TableFieldViewProps } from '@/features/custom-fields/components/table-field-view-props'
import { isRowSelected } from '@/features/custom-fields/table-field-model'

/** Mobile: one card per row, fields stacked (no wide table on 375px). */
export function TableFieldMobileView({
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
  const { t } = useTranslation()

  return (
    <ul className="flex flex-col gap-2">
      {rows.map((row, index) => (
        <li
          key={row.id ?? `new-${index}`}
          className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 shadow-sm"
        >
          <div className="flex items-center justify-between gap-2">
            <span className="text-sm font-semibold">{t('customFields.tableField.rowLabel', { index: index + 1 })}</span>
            <div className="flex items-center gap-2">
              {config.selectable ? (
                <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <TableFieldRowSelect
                    groupName={`${idPrefix}-selected-mobile`}
                    index={index}
                    checked={isRowSelected(row, config)}
                    disabled={disabled || readOnly}
                    onSelect={() => onSelect(index)}
                  />
                  {config.selectable.label}
                </label>
              ) : null}
              {canRemove ? <TableFieldRowRemove index={index} onRemove={() => onRemove(index)} /> : null}
            </div>
          </div>
          {config.columns.map((column) => (
            <TableFieldCell
              key={column.key}
              column={column}
              value={row[column.key]}
              onChange={(cell) => onCellChange(index, column.key, cell)}
              disabled={disabled}
              readOnly={readOnly}
              id={`${idPrefix}-m-${index}-${column.key}`}
              error={cellError(index, column.key)}
              showLabel
            />
          ))}
        </li>
      ))}
    </ul>
  )
}
