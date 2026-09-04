import { useTranslation } from 'react-i18next'
import { ListTree, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { RecordSection } from '@/components/detail/record-panel'
import { Can } from '@/features/auth/can'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import type { TaskSubtask } from '@/features/tasks/types'

/** Resource-level permissions gating the two affordances of this section. */
const VIEW_PERMISSION = 'tasks.view'
const CREATE_PERMISSION = 'tasks.create'

interface TaskSubtasksSectionProps {
  /** Already loaded off the parent's detail (`data.subtasks`, D-12) — this section fetches nothing. */
  subtasks: TaskSubtask[]
  /** Opens the child's own detail. */
  onOpen: (subtaskId: number) => void
  /** Opens the standard create form with `parent_task_id` prefilled and locked. */
  onCreate: () => void
  className?: string
}

interface TaskSubtaskRowProps {
  subtask: TaskSubtask
  onOpen: (subtaskId: number) => void
}

/**
 * One child row. Defined at module level, never inside the section: a
 * component redeclared per render would remount the whole list on every
 * parent update.
 */
function TaskSubtaskRow({ subtask, onOpen }: TaskSubtaskRowProps) {
  const { t } = useTranslation()
  const assignees = subtask.assignees.map((user) => user.name).join(', ')

  return (
    <li className="flex flex-col gap-1.5 px-3 py-2 @md:flex-row @md:items-center @md:gap-3">
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <Can
          permission={VIEW_PERMISSION}
          fallback={<span className="truncate text-sm">{subtask.title}</span>}
        >
          <button
            type="button"
            onClick={() => onOpen(subtask.id)}
            className="truncate rounded text-left text-sm font-medium text-foreground underline-offset-2 outline-none hover:underline focus-visible:ring-[2px] focus-visible:ring-ring/50"
          >
            {subtask.title}
          </button>
        </Can>
        <TaskLookupBadge value={subtask.task_status} />
      </div>

      <div className="flex shrink-0 items-center gap-2 @md:w-40">
        <Progress
          value={subtask.completion_percentage}
          size="xs"
          className="flex-1"
          aria-label={t('tasks.detail.completionPercentage')}
        />
        <span className="w-9 shrink-0 text-right text-xs tabular-nums text-muted-foreground">
          {t('tasks.form.percentValue', { value: subtask.completion_percentage })}
        </span>
      </div>

      <p className="min-w-0 truncate text-xs text-muted-foreground @md:w-44">
        {assignees === '' ? t('tasks.detail.noAssignees') : assignees}
      </p>
    </li>
  )
}

/**
 * "Sotto-task" (AC-085/D-12). Reads the children off `data.subtasks`, ALREADY
 * loaded with the parent detail and already filtered by the visibility scope
 * server-side (AC-066): no endpoint of its own, no second request.
 *
 * Both affordances are permission-gated through the shared `Can` — an
 * affordance only: the backend re-authorizes every call.
 */
export function TaskSubtasksSection({
  subtasks,
  onOpen,
  onCreate,
  className,
}: TaskSubtasksSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection
      title={t('tasks.detail.sections.subtasks')}
      icon={<ListTree />}
      full
      className={className}
      action={
        <Can permission={CREATE_PERMISSION}>
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            {t('tasks.detail.createSubtask')}
          </Button>
        </Can>
      }
    >
      {subtasks.length > 0 ? (
        <ul className="flex flex-col divide-y divide-border/60 rounded-lg border bg-surface">
          {subtasks.map((subtask) => (
            <TaskSubtaskRow key={subtask.id} subtask={subtask} onOpen={onOpen} />
          ))}
        </ul>
      ) : (
        <p className="text-xs text-muted-foreground">{t('tasks.detail.subtasksEmpty')}</p>
      )}
    </RecordSection>
  )
}
