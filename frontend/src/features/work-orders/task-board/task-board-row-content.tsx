/**
 * The body shared by a root row (`task-board-task-row.tsx`) and a sub-task
 * row (`task-board-subtask-row.tsx`), which differ only in the drag
 * handle/checkbox/indentation around it (D-3). On top: the title, its short
 * description cut to one line, and the "bloccato" flag; below it every value
 * is a LABELLED field of a definition list (user directive 2026-09-22), so
 * nothing on the row has to be guessed.
 */

import { useTranslation } from 'react-i18next'
import { Lock } from 'lucide-react'
import { CompletionBar } from '@/components/completion-bar'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskBoardEndDate } from '@/features/work-orders/task-board/task-board-end-date'
import {
  TaskBoardEmptyValue,
  TaskBoardHours,
  TaskBoardMetaField,
  TaskBoardPeople,
} from '@/features/work-orders/task-board/task-board-task-meta'
import type { BoardTask } from '@/features/work-orders/task-board/types'

/** Fields flow into as many ~8.5rem columns as the row allows, two per line on a phone. */
const FIELDS_GRID_CLASS = 'grid grid-cols-2 gap-x-4 gap-y-2.5 sm:grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))]'

interface TaskBoardRowContentProps {
  task: BoardTask
  today: string
  onOpen: () => void
}

export function TaskBoardRowContent({ task, today, onOpen }: TaskBoardRowContentProps) {
  const { t } = useTranslation()
  const column = (key: string) => t(`workOrders.taskBoard.task.columns.${key}`)

  return (
    <div className="flex min-w-0 flex-1 flex-col gap-2.5">
      <div className="flex min-w-0 flex-col gap-0.5">
        <div className="flex min-w-0 items-center gap-2">
          <button
            type="button"
            onClick={onOpen}
            className="min-w-0 truncate text-left text-sm font-semibold text-foreground hover:text-primary hover:underline"
          >
            {task.title}
          </button>
          {task.is_blocked ? (
            <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-destructive/10 px-1.5 py-0.5 text-xs font-medium text-destructive ring-1 ring-inset ring-destructive/30">
              <Lock className="size-3" aria-hidden="true" />
              {t('tasks.detail.blocked')}
            </span>
          ) : null}
        </div>
        {task.description_excerpt ? (
          <p className="line-clamp-1 text-xs text-muted-foreground/80" title={task.description_excerpt}>
            {task.description_excerpt}
          </p>
        ) : null}
      </div>

      <dl className={FIELDS_GRID_CLASS}>
        <TaskBoardMetaField label={column('status')}>
          <TaskLookupBadge value={task.task_status} />
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('type')}>
          {task.task_type ? <TaskLookupBadge value={task.task_type} /> : <TaskBoardEmptyValue />}
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('priority')}>
          {task.task_priority ? <TaskLookupBadge value={task.task_priority} /> : <TaskBoardEmptyValue />}
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('due')}>
          <TaskBoardEndDate task={task} today={today} />
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('requester')}>
          <TaskBoardPeople people={task.requester ? [task.requester] : []} />
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('assignees')}>
          <TaskBoardPeople people={task.assignees} />
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('watchers')}>
          <TaskBoardPeople people={task.watchers} />
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('completion')}>
          <CompletionBar
            value={task.task_status.completion_percentage}
            label={column('completion')}
            barClassName="w-14"
            className="gap-1.5"
          />
        </TaskBoardMetaField>
        <TaskBoardMetaField label={column('hours')}>
          <TaskBoardHours actualMinutes={task.actual_minutes} estimatedMinutes={task.estimated_minutes} label={column('hours')} />
        </TaskBoardMetaField>
      </dl>
    </div>
  )
}
