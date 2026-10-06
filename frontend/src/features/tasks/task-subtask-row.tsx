import { useTranslation } from 'react-i18next'
import { Circle, CircleCheck, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CompletionBar } from '@/components/completion-bar'
import { UserAvatarStack } from '@/components/user-avatar-stack'
import { Can } from '@/features/auth/can'
import { cn } from '@/lib/utils'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { isSubtaskDone } from '@/features/tasks/task-subtask-progress'
import type { TaskSubtask } from '@/features/tasks/types'

/** Resource-level permission gating the child rows' own affordance (unrelated to `create_subtask`). */
const VIEW_PERMISSION = 'tasks.view'

const TOGGLE_CLASS =
  'flex size-6 shrink-0 items-center justify-center rounded-full outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 [&>svg]:size-4.5'

interface TaskSubtaskRowProps {
  subtask: TaskSubtask
  onOpen: (subtaskId: number) => void
  onComplete: (subtask: TaskSubtask) => void
  onReopen: (subtask: TaskSubtask) => void
  onDelete: (subtask: TaskSubtask) => void
  isBusy: boolean
}

type ToggleProps = Omit<TaskSubtaskRowProps, 'onOpen' | 'onDelete'>

/**
 * The row's leading check: an empty circle that completes the child, a
 * green check that reopens it. Without either action it is a plain marker
 * of the same shape, so the column always lines up.
 */
function SubtaskCompletionToggle({ subtask, onComplete, onReopen, isBusy }: ToggleProps) {
  const { t } = useTranslation()
  const { actions } = subtask.permissions

  if (actions.complete) {
    return (
      <button
        type="button"
        disabled={isBusy}
        onClick={() => onComplete(subtask)}
        aria-label={t('tasks.detail.subtaskPanel.complete')}
        title={t('tasks.detail.subtaskPanel.complete')}
        className={cn(TOGGLE_CLASS, 'group/toggle text-muted-foreground hover:text-success')}
      >
        <Circle className="group-hover/toggle:hidden" aria-hidden="true" />
        <CircleCheck className="hidden group-hover/toggle:block" aria-hidden="true" />
      </button>
    )
  }
  if (actions.uncomplete) {
    return (
      <button
        type="button"
        disabled={isBusy}
        onClick={() => onReopen(subtask)}
        aria-label={t('tasks.detail.subtaskPanel.reopen')}
        title={t('tasks.detail.subtaskPanel.reopen')}
        className={cn(TOGGLE_CLASS, 'text-success hover:text-muted-foreground')}
      >
        <CircleCheck aria-hidden="true" />
      </button>
    )
  }
  return (
    <span className={cn(TOGGLE_CLASS, isSubtaskDone(subtask) ? 'text-success' : 'text-muted-foreground/60')}>
      {isSubtaskDone(subtask) ? <CircleCheck aria-hidden="true" /> : <Circle aria-hidden="true" />}
    </span>
  )
}

/**
 * One child row of the "Sotto-task" panel: check, title + status, its own
 * completion, the assignees as the app's avatar stack and the delete action
 * (revealed on hover with a mouse, always on touch and on keyboard focus).
 */
export function TaskSubtaskRow({ subtask, onOpen, onComplete, onReopen, onDelete, isBusy }: TaskSubtaskRowProps) {
  const { t } = useTranslation()
  const done = isSubtaskDone(subtask)

  return (
    <div className="group flex flex-1 flex-col gap-1.5 @md:flex-row @md:items-center @md:gap-3">
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <SubtaskCompletionToggle subtask={subtask} onComplete={onComplete} onReopen={onReopen} isBusy={isBusy} />
        <Can
          permission={VIEW_PERMISSION}
          fallback={<span className={cn('truncate text-sm', done && 'text-muted-foreground line-through')}>{subtask.title}</span>}
        >
          <button
            type="button"
            onClick={() => onOpen(subtask.id)}
            className={cn(
              'truncate rounded text-left text-sm font-medium text-foreground underline-offset-2 outline-none hover:text-primary hover:underline focus-visible:ring-[2px] focus-visible:ring-ring/50',
              done && 'text-muted-foreground line-through decoration-muted-foreground/60',
            )}
          >
            {subtask.title}
          </button>
        </Can>
        <TaskLookupBadge value={subtask.task_status} />
      </div>

      <div className="flex items-center gap-3 pl-8 @md:pl-0">
        <CompletionBar
          value={subtask.completion_percentage}
          label={t('tasks.detail.completionPercentage')}
          barClassName="flex-1"
          valueClassName="w-9 text-right"
          className="min-w-0 flex-1 @md:w-36 @md:flex-none"
        />

        <div className="flex shrink-0 items-center @md:w-28">
          {subtask.assignees.length > 0 ? (
            <UserAvatarStack users={subtask.assignees} />
          ) : (
            <span className="truncate text-xs text-muted-foreground">{t('tasks.detail.noAssignees')}</span>
          )}
        </div>

        <div className="flex size-7 shrink-0 items-center justify-center">
          {subtask.permissions.actions.delete ? (
            <Button
              type="button"
              variant="ghost"
              size="icon"
              disabled={isBusy}
              onClick={() => onDelete(subtask)}
              aria-label={t('tasks.detail.subtaskPanel.delete')}
              className="size-7 text-muted-foreground hover:text-destructive focus-visible:opacity-100 pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100"
            >
              <Trash2 className="size-3.5" aria-hidden="true" />
            </Button>
          ) : null}
        </div>
      </div>
    </div>
  )
}
