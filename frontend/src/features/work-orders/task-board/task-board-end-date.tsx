/**
 * The task's "Data fine" (user directive 2026-09-22): the date alone when all
 * is well; the shared `DueDateChip` once it has passed on an open task, or
 * when it falls today. The overdue/today rules are the SAME ones the KPI
 * strip counts with (`task-board-metrics.ts`), so row and KPI never disagree.
 */

import { useTranslation } from 'react-i18next'
import { DueDateChip } from '@/components/due-date-chip'
import { isDueToday, isOverdue } from '@/features/work-orders/task-board/task-board-metrics'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { formatDate } from '@/lib/formatting/date-display'

interface TaskBoardEndDateProps {
  task: BoardTask
  today: string
}

export function TaskBoardEndDate({ task, today }: TaskBoardEndDateProps) {
  const { t } = useTranslation()

  if (task.end_date === null) {
    return <span className="text-xs text-muted-foreground">—</span>
  }

  const date = formatDate(task.end_date)
  const overdue = isOverdue(task, today)
  const dueToday = !overdue && isDueToday(task, today)

  if (!overdue && !dueToday) {
    return <span className="text-xs tabular-nums text-foreground">{date}</span>
  }

  return overdue ? (
    <DueDateChip date={date} state="overdue" label={t('workOrders.taskBoard.task.overdue')} />
  ) : (
    <DueDateChip date={date} state="today" label={t('workOrders.taskBoard.task.dueToday')} />
  )
}
