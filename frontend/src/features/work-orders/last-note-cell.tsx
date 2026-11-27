import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { EmptyCell } from '@/features/table/cell-renderers'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

/**
 * Grid cells `last_note_at` / `supervisor_last_note_at` (legacy `OrdersTable`
 * "Ultima Nota" / "U.N. Responsabile"): the note date, its author, time and
 * text on hover. The row carries `<column>_author` and `<column>_excerpt`.
 */
export function LastNoteCell({ value, data, colDef }: ICellRendererParams) {
  const { t } = useTranslation()
  const date = formatDate(value)
  const field = colDef?.field ?? ''
  const row = (data ?? {}) as Record<string, unknown>
  const author = typeof row[`${field}_author`] === 'string' ? (row[`${field}_author`] as string) : ''
  const excerpt = typeof row[`${field}_excerpt`] === 'string' ? (row[`${field}_excerpt`] as string) : ''

  if (date === '') {
    return <EmptyCell align="left" />
  }

  const hint = t('workOrders.lastNote.hint', { author, date: formatDateTime(value) })

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <span tabIndex={0} aria-label={`${hint}: ${excerpt}`} className="cursor-default tabular-nums text-foreground">
            {date}
          </span>
        </TooltipTrigger>
        <TooltipContent side="top" variant="light" className="max-w-72 space-y-1 text-xs">
          <p className="font-medium">{hint}</p>
          {excerpt !== '' ? <p className="whitespace-pre-line text-muted-foreground">{excerpt}</p> : null}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
