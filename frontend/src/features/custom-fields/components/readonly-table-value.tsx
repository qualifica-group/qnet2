import { Check } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { formatTableCell } from '@/components/data-table/format-table-row-inline'
import type {
  TableFieldConfig,
  TableFieldRow,
  TableFieldValue,
} from '@/features/custom-fields/types'

const HEADER_CELL = 'px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'
const BODY_CELL = 'px-2 py-1.5 align-top'

interface ReadonlyTableValueProps {
  config: TableFieldConfig
  value: TableFieldValue
}

/**
 * Read-only rendering of a `table` field value (spec 0180, AC-023): a compact
 * table whose overflow scrolls inside its own container. The selected row is
 * marked with a check icon plus the selectable column's label for screen
 * readers, not by colour alone.
 */
export function ReadonlyTableValue({ config, value }: ReadonlyTableValueProps) {
  const { t } = useTranslation()
  const selectable = config.selectable ?? null

  if (value.rows.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('customFields.tableField.empty')}</p>
  }

  return (
    <div className="max-w-full overflow-x-auto rounded-lg border border-border">
      <table className="w-full text-sm">
        <thead className="bg-surface">
          <tr>
            {selectable ? (
              <th scope="col" className={HEADER_CELL}>
                {selectable.label}
              </th>
            ) : null}
            {config.columns.map((column) => (
              <th key={column.key} scope="col" className={HEADER_CELL}>
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {value.rows.map((row: TableFieldRow, index) => {
            const selected = selectable !== null && row[selectable.key] === true
            return (
              <tr
                key={row.id ?? `row-${index}`}
                className={selected ? 'border-t border-border bg-muted/40 font-medium' : 'border-t border-border'}
              >
                {selectable ? (
                  <td className={BODY_CELL}>
                    {selected ? (
                      <>
                        <Check className="size-3.5" aria-hidden="true" />
                        <span className="sr-only">{selectable.label}</span>
                      </>
                    ) : null}
                  </td>
                ) : null}
                {config.columns.map((column) => (
                  <td key={column.key} className={BODY_CELL}>
                    {formatTableCell(column, row[column.key], t)}
                  </td>
                ))}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
