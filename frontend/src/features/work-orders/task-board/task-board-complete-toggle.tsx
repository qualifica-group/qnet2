/**
 * The check before a task's title (spec 0401 D-3), drawn exactly as the Task
 * grid's title cell (`task-complete-icon-styles.ts`): a completable task gets
 * a muted toggle that opens the usual "Completa" dialog; a completed one the
 * filled green icon, which reopens it when the actor may; anything else a
 * faded icon that only keeps the titles aligned.
 */

import { useContext } from 'react'
import { useTranslation } from 'react-i18next'
import { CheckCircle2 } from 'lucide-react'
import {
  COMPLETABLE_ICON_CLASS,
  COMPLETED_ICON_CLASS,
  COMPLETE_ICON_CLASS,
  COMPLETE_TOGGLE_CLASS,
  INERT_ICON_CLASS,
} from '@/features/tasks/task-complete-icon-styles'
import { TaskBoardCellEditContext } from '@/features/work-orders/task-board/task-board-row-columns'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { cn } from '@/lib/utils'

/** The filled icon as the "Riapri" button: a fade on hover says it is clickable. */
const REOPEN_TOGGLE_CLASS =
  'cursor-pointer rounded-full outline-none transition-opacity hover:opacity-70 focus-visible:ring-2 focus-visible:ring-ring'

export function TaskBoardCompleteToggle({ task }: { task: BoardTask }) {
  const { t } = useTranslation()
  const cellEdit = useContext(TaskBoardCellEditContext)
  const { actions } = task.permissions
  const isCompleted = task.task_status.group === 'closed_positive'

  if (isCompleted && actions.uncomplete && cellEdit) {
    return (
      <button
        type="button"
        onClick={() => cellEdit.reopen(task)}
        aria-label={t('tasks.actions.uncomplete.label')}
        title={t('tasks.actions.uncomplete.label')}
        className={REOPEN_TOGGLE_CLASS}
      >
        <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, COMPLETED_ICON_CLASS)} />
      </button>
    )
  }

  if (isCompleted) {
    return (
      <CheckCircle2
        role="img"
        aria-label={t('tasks.actions.complete.done')}
        className={cn(COMPLETE_ICON_CLASS, COMPLETED_ICON_CLASS)}
      />
    )
  }

  if (actions.complete && cellEdit) {
    return (
      <button
        type="button"
        onClick={() => cellEdit.complete(task)}
        aria-label={t('tasks.actions.complete.label')}
        title={t('tasks.actions.complete.label')}
        className={COMPLETE_TOGGLE_CLASS}
      >
        <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, COMPLETABLE_ICON_CLASS)} />
      </button>
    )
  }

  return <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, INERT_ICON_CLASS)} />
}
