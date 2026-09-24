import type { ICellRendererParams } from 'ag-grid-community'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'

/** `actual_minutes` (spec 0156 D-2): the sum of every segnatempo on the task, formatted like every other minutes figure of the module. */
export function ActualMinutesCell({ value }: ICellRendererParams) {
  const minutes = typeof value === 'number' ? value : Number(value)
  return <span className="tabular-nums text-foreground">{Number.isFinite(minutes) ? formatMinutesLabel(minutes) : ''}</span>
}
