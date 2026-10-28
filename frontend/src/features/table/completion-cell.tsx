import type { ICellRendererParams } from 'ag-grid-community'
import { CompletionBar } from '@/components/completion-bar'
import { EmptyCell } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'

interface CompletionCellProps extends ICellRendererParams {
  /** Accessible name of the bar (the column's label). */
  label: string
  /** Row field holding a date shown under the bar (e.g. when the figure was recorded); omitted = bar only. */
  dateField?: string
}

/** Grid cell for a derived 0..100 completion column: the shared `CompletionBar`, empty on a non-numeric value. */
export function CompletionCell({ value, label, dateField, data }: CompletionCellProps) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }
  if (dateField === undefined) {
    return <CompletionBar value={value} label={label} className="h-full" />
  }
  const date = formatDate((data as Record<string, unknown> | undefined)?.[dateField])
  return (
    <span className="flex h-full flex-col justify-center gap-0.5 py-1 leading-none">
      <CompletionBar value={value} label={label} />
      {date ? <span className="text-[11px] text-muted-foreground tabular-nums">{date}</span> : null}
    </span>
  )
}
