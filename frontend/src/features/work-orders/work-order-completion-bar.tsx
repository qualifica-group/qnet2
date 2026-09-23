import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { EmptyCell } from '@/features/table/cell-renderers'
import { completionTone } from '@/features/work-orders/task-board/task-board-completion-tone'

interface WorkOrderCompletionBarProps {
  /** `completion_percentage`, computed server-side from the root tasks (spec 0149 D-5). */
  value: number
  className?: string
}

/**
 * The commessa's completion (spec 0149): a compact bar plus the figure, both
 * coloured by the SAME `completionTone` rule the task board uses, so the
 * detail header, the grid and the board read the same number the same way.
 */
export function WorkOrderCompletionBar({ value, className }: WorkOrderCompletionBarProps) {
  const { t } = useTranslation()
  const tone = completionTone(value)
  const label = t('tasks.form.percentValue', { value })

  return (
    <span className={cn('flex items-center gap-2', className)}>
      <Progress
        value={value}
        size="xs"
        className={cn('w-16', tone.track)}
        indicatorClassName={tone.indicator}
        aria-label={t('workOrders.columns.completion_percentage')}
      />
      <span className={cn('text-xs font-semibold tabular-nums', tone.text)}>{label}</span>
    </span>
  )
}

/** The grid's `completion_percentage` cell (spec 0149 AC-015): the same bar, empty on a non-numeric value. */
export function WorkOrderCompletionCell({ value }: ICellRendererParams) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }
  return <WorkOrderCompletionBar value={value} className="h-full" />
}
