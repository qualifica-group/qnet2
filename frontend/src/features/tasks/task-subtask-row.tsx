import { useTranslation } from 'react-i18next'
import { CheckCircle2, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CompletionBar } from '@/components/completion-bar'
import { UserAvatarStack } from '@/components/user-avatar-stack'
import { Can } from '@/features/auth/can'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'
import {
  COMPLETABLE_ICON_CLASS,
  COMPLETED_ICON_CLASS,
  COMPLETE_ICON_CLASS,
  COMPLETE_TOGGLE_CLASS,
  INERT_ICON_CLASS,
} from '@/features/tasks/task-complete-icon-styles'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { isSubtaskDone } from '@/features/tasks/task-subtask-progress'
import type { TaskLookupRef, TaskSubtask } from '@/features/tasks/types'

/** Resource-level permission gating the child rows' own affordance (unrelated to `create_subtask`). */
const VIEW_PERMISSION = 'tasks.view'

/** A done child that may be reopened: the filled icon stays, a fade on hover says it is clickable. */
const REOPEN_TOGGLE_CLASS =
  'cursor-pointer rounded-full outline-none transition-opacity hover:opacity-70 focus-visible:ring-2 focus-visible:ring-ring'

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
 * The check before the title, drawn exactly as the grid's title cell draws
 * it (`task-complete-icon-styles.ts`): filled green when the child is done,
 * a muted toggle that turns green on hover when it can be completed, faded
 * otherwise. A done child the actor may reopen keeps its filled icon as the
 * "Riapri" button.
 */
function SubtaskCompleteToggle({ subtask, onComplete, onReopen, isBusy }: ToggleProps) {
  const { t } = useTranslation()
  const { actions } = subtask.permissions
  const done = isSubtaskDone(subtask)

  if (actions.complete) {
    return (
      <button
        type="button"
        disabled={isBusy}
        onClick={() => onComplete(subtask)}
        aria-label={t('tasks.detail.subtaskPanel.complete')}
        title={t('tasks.detail.subtaskPanel.complete')}
        className={COMPLETE_TOGGLE_CLASS}
      >
        <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, COMPLETABLE_ICON_CLASS)} />
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
        className={REOPEN_TOGGLE_CLASS}
      >
        <CheckCircle2
          aria-hidden="true"
          className={cn(COMPLETE_ICON_CLASS, done ? COMPLETED_ICON_CLASS : 'text-muted-foreground')}
        />
      </button>
    )
  }
  if (done) {
    return (
      <CheckCircle2
        role="img"
        aria-label={t('tasks.actions.complete.done')}
        className={cn(COMPLETE_ICON_CLASS, COMPLETED_ICON_CLASS)}
      />
    )
  }
  return <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, INERT_ICON_CLASS)} />
}

/**
 * The child's type as a small tile in the type's own colour and icon (the
 * same token seam as every badge, `badgeColorClass`), named on hover and for
 * assistive tech. A type with no icon shows its colour dot instead.
 */
function SubtaskTypeIcon({ type }: { type: TaskLookupRef }) {
  return (
    <span
      role="img"
      aria-label={type.name}
      title={type.name}
      className={cn(
        'flex size-6 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground',
        badgeColorClass(type.color),
      )}
    >
      {type.icon ? (
        <DynamicIcon name={type.icon} className="size-3.5" />
      ) : (
        <span className={cn('size-2 rounded-full bg-current', swatchClassFor(type.color))} aria-hidden="true" />
      )}
    </span>
  )
}

/**
 * One child row of the "Sotto-task" panel: the complete check, the type
 * tile, title + status, its own completion, the assignees as the app's
 * avatar stack and the delete action (revealed on hover with a mouse, always
 * on touch and on keyboard focus). The row's own tint comes from the type
 * (`tintClassFor`, applied by the section on the list item).
 */
export function TaskSubtaskRow({ subtask, onOpen, onComplete, onReopen, onDelete, isBusy }: TaskSubtaskRowProps) {
  const { t } = useTranslation()
  const done = isSubtaskDone(subtask)

  return (
    <div className="group flex flex-1 flex-col gap-1.5 @md:flex-row @md:items-center @md:gap-3">
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <SubtaskCompleteToggle subtask={subtask} onComplete={onComplete} onReopen={onReopen} isBusy={isBusy} />
        {subtask.task_type ? <SubtaskTypeIcon type={subtask.task_type} /> : null}
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
