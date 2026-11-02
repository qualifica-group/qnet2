/**
 * Tri-state "select all" checkbox of the Task board's bulk selection (D-7):
 * one per fase header (list and kanban) and one in the toolbar for the whole
 * board. It covers only the ROOT tasks it is handed, i.e. the ones visible
 * under the current filters, the same rows that carry their own checkbox.
 */

import { Checkbox } from '@/components/ui/checkbox'
import { selectAllState } from '@/features/work-orders/task-board/task-board-selection'

interface TaskBoardSelectAllCheckboxProps {
  taskIds: number[]
  selectedTaskIds: ReadonlySet<number>
  onSetSelection: (taskIds: number[], isSelected: boolean) => void
  label: string
}

export function TaskBoardSelectAllCheckbox({ taskIds, selectedTaskIds, onSetSelection, label }: TaskBoardSelectAllCheckboxProps) {
  const state = selectAllState(taskIds, selectedTaskIds)

  return (
    <Checkbox
      checked={state}
      // A partial selection completes to "all", as in the data tables' header checkbox.
      onCheckedChange={() => onSetSelection(taskIds, state !== true)}
      disabled={taskIds.length === 0}
      aria-label={label}
      title={label}
      className="shrink-0"
    />
  )
}
