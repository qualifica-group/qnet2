import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { PlusCircle } from 'lucide-react'
import { formatDate } from '@/lib/formatting/date-display'

interface WorkOrderProgressCellProps extends ICellRendererParams {
  /** Opens the progress popup of the given work order. */
  onOpen: (workOrderId: number) => void
}

/**
 * Grid cell `completion_percentage` (spec 0321 R-6, legacy `avanzamento_commessa`):
 * "{total}%" with the last date below, or a plus icon when nothing is recorded;
 * the whole cell is a button that opens the progress popup.
 */
export function WorkOrderProgressCell({ value, data, onOpen }: WorkOrderProgressCellProps) {
  const { t } = useTranslation()
  const row = data as { id?: number; completion_recorded_on?: string | null } | undefined
  if (row?.id === undefined) {
    return null
  }
  const workOrderId = Number(row.id)
  const date = formatDate(row.completion_recorded_on)
  const hasEntries = Boolean(row.completion_recorded_on) && typeof value === 'number'

  return (
    <button
      type="button"
      aria-label={t('workOrders.progress.open')}
      onClick={() => onOpen(workOrderId)}
      className="flex size-full cursor-pointer flex-col items-center justify-center rounded-sm leading-tight hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring"
    >
      {hasEntries ? (
        <>
          <span className="text-sm tabular-nums">{value}%</span>
          <span className="text-[11px] text-muted-foreground tabular-nums">{date}</span>
        </>
      ) : (
        <PlusCircle className="size-4 text-muted-foreground" aria-hidden="true" />
      )}
    </button>
  )
}
