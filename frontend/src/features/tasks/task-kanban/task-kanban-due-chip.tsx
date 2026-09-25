/**
 * The card's own "Data fine" chip (spec 0157 D-2), styled like the commessa
 * board's `TaskBoardEndDate` (same red/amber chip idea) but driven by THIS
 * board's own bucket classification instead of `BoardTask`'s fields, so it
 * has no dependency on the board's types.
 */
import { useTranslation } from 'react-i18next'
import { AlertTriangle, CalendarClock } from 'lucide-react'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { classifyTaskDueBucket } from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

const CHIP_CLASS = 'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums'
const OVERDUE_CLASS = 'bg-destructive/10 text-destructive ring-1 ring-inset ring-destructive/30'
const TODAY_CLASS = 'bg-primary/10 text-primary ring-1 ring-inset ring-primary/20'

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
  const overdue = bucket === 'overdue'
  const dueToday = bucket === 'today'

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
