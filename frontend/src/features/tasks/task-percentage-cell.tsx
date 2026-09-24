import i18n from '@/i18n'
import { Progress } from '@/components/ui/progress'
import { cn } from '@/lib/utils'
import { EmptyCell } from '@/features/table/cell-renderers'
import { completionTone } from '@/features/work-orders/task-board/task-board-completion-tone'
import type { ICellRendererParams } from 'ag-grid-community'

/**
 * The derived "Percentuale completamento" column (D-6/AC-022): a compact bar
 * plus the value, coloured by the shared `completionTone` rule so the grid reads
 * the number like the task board and the commessa. Read-only by construction — the number is a projection of
 * the row's status, computed server-side by `TaskStatusResolver`, so the grid
 * only displays it.
 */
export function TaskPercentageCell({ value }: ICellRendererParams) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }

  const label = i18n.t('tasks.form.percentValue', { value })
  const tone = completionTone(value)

  return (
    <div className="flex h-full items-center gap-2">
      <Progress
        value={value}
        size="xs"
        className={cn('w-16', tone.track)}
        indicatorClassName={tone.indicator}
        aria-label={label}
      />
      <span className={cn('text-xs font-semibold tabular-nums', tone.text)}>{label}</span>
    </div>
  )
}
