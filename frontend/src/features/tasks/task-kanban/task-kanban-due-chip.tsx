/**
 * The card's own "Data fine" chip (spec 0157 D-2): the commessa board's
 * `DueDateChip`, driven by THIS board's own bucket classification instead of
 * `BoardTask`'s fields, so it has no dependency on the board's types.
 */
import { useTranslation } from 'react-i18next'
import { DueDateChip } from '@/components/due-date-chip'
import { classifyTaskDueBucket } from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'
import { formatDate } from '@/lib/formatting/date-display'

interface TaskKanbanDueChipProps {
  row: TaskKanbanRow
  today: string
}

export function TaskKanbanDueChip({ row, today }: TaskKanbanDueChipProps) {
  const { t } = useTranslation()

  if (row.end_date === null) {
    return <span className="text-xs text-muted-foreground">—</span>
  }

  const date = formatDate(row.end_date)
  const bucket = classifyTaskDueBucket(row, today)

  if (bucket === 'overdue') {
    return <DueDateChip date={date} state="overdue" label={t('workOrders.taskBoard.task.overdue')} />
  }
  if (bucket === 'today') {
    return <DueDateChip date={date} state="today" label={t('workOrders.taskBoard.task.dueToday')} />
  }

  return <span className="text-xs tabular-nums text-foreground">{date}</span>
}
