import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { DueDateChip } from '@/components/due-date-chip'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { EmptyCell } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { taskEndDateState } from '@/features/tasks/task-due-state'
import { todayIsoDate } from '@/features/work-order-costs/work-order-costs-schema'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

interface TaskEndDateProps {
  endDate: string | null
  /** The task's status phase: a closed task is never late. `null` when unknown (create). */
  statusGroup: TaskStatusGroupValue | null
}

/**
 * A task's "Data fine" as the commessa board shows it: the plain date, or the
 * shared `DueDateChip` once it has passed on an open task or falls today.
 * Used by the Task grid cell and the Task detail.
 */
export function TaskEndDate({ endDate, statusGroup }: TaskEndDateProps) {
  const { t } = useTranslation()
  const date = formatDate(endDate)

  if (date === '') {
    return <DetailEmpty />
  }

  const state = taskEndDateState(endDate, statusGroup, todayIsoDate())
  if (state === null) {
    return <span className="tabular-nums">{date}</span>
  }

  const label = t(state === 'overdue' ? 'workOrders.taskBoard.task.overdue' : 'workOrders.taskBoard.task.dueToday')
  return <DueDateChip date={date} state={state} label={label} />
}

/** The grid's "Data fine" cell: the row carries its status `group` (spec 0156 D-2). */
export function TaskEndDateCell({ value, data }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }

  return <TaskEndDate endDate={value} statusGroup={data?.task_status?.group ?? null} />
}
