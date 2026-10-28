import type { ICellRendererParams } from 'ag-grid-community'
import { CompletionBar } from '@/components/completion-bar'
import { EmptyCell } from '@/features/table/cell-renderers'

interface CompletionCellProps extends ICellRendererParams {
  /** Accessible name of the bar (the column's label). */
  label: string
}

/** Grid cell for a derived 0..100 completion column: the shared `CompletionBar`, empty on a non-numeric value. */
export function CompletionCell({ value, label }: CompletionCellProps) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }
  return <CompletionBar value={value} label={label} className="h-full" />
}
