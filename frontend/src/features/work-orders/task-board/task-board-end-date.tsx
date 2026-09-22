/**
 * The task's "Data fine" (user directive 2026-09-22): the date alone when all
 * is well; wrapped in a red chip with the alert triangle and a "Scaduto"
 * tooltip once it has passed on an open task; tinted with a "Oggi" tooltip
 * when it falls today. The overdue/today rules are the SAME ones the KPI
 * strip counts with (`task-board-metrics.ts`), so row and KPI never disagree.
 * State is never colour-only: each variant has its own icon and an accessible
 * name carrying the word.
 */

import { useTranslation } from 'react-i18next'
import { AlertTriangle, CalendarClock } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { isDueToday, isOverdue } from '@/features/work-orders/task-board/task-board-metrics'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

const CHIP_CLASS = 'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums'
const OVERDUE_CLASS = 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/30'
const TODAY_CLASS = 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20'

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

  const label = overdue ? t('workOrders.taskBoard.task.overdue') : t('workOrders.taskBoard.task.dueToday')
  const Icon = overdue ? AlertTriangle : CalendarClock

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span
          tabIndex={0}
          aria-label={`${date}, ${label}`}
          className={cn(CHIP_CLASS, 'outline-none focus-visible:ring-2', overdue ? OVERDUE_CLASS : TODAY_CLASS)}
        >
          <Icon className="size-3 shrink-0" aria-hidden="true" />
          {date}
        </span>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}
