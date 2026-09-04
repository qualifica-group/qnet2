import i18n from '@/i18n'
import { Progress } from '@/components/ui/progress'
import { EmptyCell } from '@/features/table/cell-renderers'
import type { ICellRendererParams } from 'ag-grid-community'

/**
 * The derived "Percentuale completamento" column (D-6/AC-022): a compact bar
 * plus the value. Read-only by construction — the number is a projection of
 * the row's status, computed server-side by `TaskStatusResolver`, so the grid
 * only displays it.
 */
export function TaskPercentageCell({ value }: ICellRendererParams) {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return <EmptyCell />
  }

  const label = i18n.t('tasks.form.percentValue', { value })

  return (
    <div className="flex h-full items-center gap-2">
      <Progress value={value} size="xs" className="w-16" aria-label={label} />
      <span className="text-xs tabular-nums text-muted-foreground">{label}</span>
    </div>
  )
}
