import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import {
  findSelectedRow,
  formatTableRowInline,
} from '@/components/data-table/format-table-row-inline'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { ReadonlyTableValue } from '@/features/custom-fields/components/readonly-table-value'
import type { TableFieldConfig, TableFieldValue } from '@/features/custom-fields/types'
import { formatDateTimeOptionalTime } from '@/lib/formatting/date-display'

const ISO_DATE_LIKE = /^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?/

export interface TableSummaryCellProps {
  /** Definition of the column; absent on older payloads, which degrade to summary + count with no tooltip. */
  table?: TableFieldConfig
}

/** ISO date/datetime summaries are shown in the user's pattern; anything else as is. */
function formatSummary(summary: TableFieldValue['summary']): string {
  if (summary === null || summary === undefined) {
    return ''
  }
  if (typeof summary === 'string' && ISO_DATE_LIKE.test(summary)) {
    return formatDateTimeOptionalTime(summary) || summary
  }
  return String(summary)
}

function isTableValue(value: unknown): value is TableFieldValue {
  return typeof value === 'object' && value !== null && Array.isArray((value as TableFieldValue).rows)
}

/**
 * Grid cell of a `type:'table'` column (spec 0180, AC-022): the selected row
 * inline when the definition has a selectable column and a row is flagged,
 * else the formatted summary plus the row count. With the definition at hand
 * a keyboard-focusable trigger opens a read-only table of every row. Blank
 * for a null value or no rows.
 */
export function TableSummaryCell({ value, table }: ICellRendererParams & TableSummaryCellProps) {
  const { t } = useTranslation()
  if (!isTableValue(value) || value.rows.length === 0) {
    return null
  }

  const count = t('customFields.tableField.rowsCount', { count: value.rows.length })
  const selected = table ? findSelectedRow(table, value.rows) : undefined
  const inline = table && selected ? formatTableRowInline(table, selected, t) : ''
  const summary = formatSummary(value.summary)
  const visible = inline || (summary ? `${summary} · ${value.rows.length}` : count)
  const accessible = inline ? `${inline}, ${count}` : summary ? `${summary}, ${count}` : count

  const label = (
    <span className="block truncate">
      <span aria-hidden="true">{visible}</span>
      <span className="sr-only">{accessible}</span>
    </span>
  )
  if (!table) {
    return <span title={accessible}>{label}</span>
  }

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <span tabIndex={0} className="block cursor-default truncate rounded-sm focus-visible:outline-2 focus-visible:outline-ring">
            {label}
          </span>
        </TooltipTrigger>
        <TooltipContent side="top" variant="light" className="max-h-64 max-w-[min(32rem,90vw)] overflow-auto p-2">
          <ReadonlyTableValue config={table} value={value} />
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
