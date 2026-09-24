import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2, ListTree, Plus, RotateCcw, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CompletionBar } from '@/components/completion-bar'
import { RecordSection } from '@/components/detail/record-panel'
import { SortableList, type SortableListItem } from '@/components/ui/sortable-list'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { deleteTask, fetchTask, taskDetailQueryKey, uncompleteTask } from '@/features/tasks/api'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { useReorderTaskSubtasks } from '@/features/tasks/use-task-mutations'
import type { TaskSubtask } from '@/features/tasks/types'

/** Resource-level permission gating the child rows' own affordance (unrelated to `create_subtask`). */
const VIEW_PERMISSION = 'tasks.view'

interface TaskSubtasksSectionProps {
  /** The parent's own id (spec 0155 D-4): the reorder endpoint and every cache invalidation below target it. */
  parentTaskId: number
  /** Already loaded off the parent's detail (`data.subtasks`, D-12) — this section fetches nothing for the LIST. */
  subtasks: TaskSubtask[]
  /** Opens the child's own detail. */
  onOpen: (subtaskId: number) => void
  /** Opens the standard create form with `parent_task_id` prefilled and locked. */
  onCreate: () => void
  /**
   * `permissions.actions.create_subtask` (spec 0123 D-9): `tasks.create` AND
   * neither this task nor an ancestor is write-locked. Gates the button
   * directly — no `<Can>` ability check alongside it, the flag already IS
   * that ability ANDed with the availability rule (AC-036).
   */
  canCreateSubtask: boolean
  /**
   * Spec 0155 D-4/AC-006: reordering requires `update` on the PARENT (403
   * without it) — gates the drag handle only, the server re-authorizes every
   * drop.
   */
  canReorder: boolean
  className?: string
}

interface SortableSubtaskItem extends SortableListItem {
  subtask: TaskSubtask
}

interface TaskSubtaskRowProps {
  subtask: TaskSubtask
  onOpen: (subtaskId: number) => void
  onComplete: (subtask: TaskSubtask) => void
  onReopen: (subtask: TaskSubtask) => void
  onDelete: (subtask: TaskSubtask) => void
  isBusy: boolean
}

/**
 * One child row. Defined at module level, never inside the section: a
 * component redeclared per render would remount the whole list on every
 * parent update.
 */
function TaskSubtaskRow({
  subtask,
  onOpen,
  onComplete,
  onReopen,
  onDelete,
  isBusy,
}: TaskSubtaskRowProps) {
  const { t } = useTranslation()
  const assignees = subtask.assignees.map((user) => user.name).join(', ')

  return (
    <div className="flex flex-1 flex-col gap-1.5 @md:flex-row @md:items-center @md:gap-3">
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

      <CompletionBar
        value={subtask.completion_percentage}
        label={t('tasks.detail.completionPercentage')}
        barClassName="flex-1"
        valueClassName="w-9 text-right"
        className="shrink-0 @md:w-40"
      />

      <p className="min-w-0 truncate text-xs text-muted-foreground @md:w-44">
        {assignees === '' ? t('tasks.detail.noAssignees') : assignees}
      </p>

      <div className="flex shrink-0 items-center gap-0.5">
        {subtask.permissions.actions.complete ? (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={isBusy}
            onClick={() => onComplete(subtask)}
            aria-label={t('tasks.detail.subtaskPanel.complete')}
          >
            <CheckCircle2 className="size-3.5" aria-hidden="true" />
          </Button>
        ) : null}
        {subtask.permissions.actions.uncomplete ? (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={isBusy}
            onClick={() => onReopen(subtask)}
            aria-label={t('tasks.detail.subtaskPanel.reopen')}
          >
            <RotateCcw className="size-3.5" aria-hidden="true" />
          </Button>
        ) : null}
        {subtask.permissions.actions.delete ? (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            disabled={isBusy}
            onClick={() => onDelete(subtask)}
            aria-label={t('tasks.detail.subtaskPanel.delete')}
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        ) : null}
      </div>
    </div>
  )
}

/**
 * "Sotto-task" (AC-085/D-12, spec 0155 D-4/D-5). Reads the children off
 * `data.subtasks`, ALREADY loaded with the parent detail (AC-066) — no
 * endpoint of its own for the LIST. Reorder, complete, reopen and delete each
 * reuse the task module's own generic endpoints against the child's own id,
 * then invalidate the PARENT's cached detail so this panel reflects the
 * fresh `subtasks[]` (position/permissions included).
 */
export function TaskSubtasksSection({
  parentTaskId,
  subtasks,
  onOpen,
  onCreate,
  canCreateSubtask,
  canReorder,
  className,
}: TaskSubtasksSectionProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const confirm = useConfirm()
  const reorderMutation = useReorderTaskSubtasks(parentTaskId)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [completingId, setCompletingId] = useState<number | null>(null)

  const { data: completingTask, isLoading: completingLoading } = useEntityDetail(
    taskDetailQueryKey(completingId ?? -1),
    () => fetchTask(completingId as number),
    completingId !== null,
  )

  const items = useMemo<SortableSubtaskItem[]>(
    () => subtasks.map((subtask) => ({ id: String(subtask.id), subtask })),
    [subtasks],
  )

  async function invalidateParent() {
    await queryClient.invalidateQueries({ queryKey: taskDetailQueryKey(parentTaskId) })
  }

  function handleReorder(orderedIds: string[]) {
    reorderMutation.mutate(
      orderedIds.map(Number),
      { onError: () => toast.error(t('tasks.detail.subtaskPanel.reorderError')) },
    )
  }

  async function handleReopen(subtask: TaskSubtask) {
    const confirmed = await confirm({
      title: t('tasks.detail.subtaskPanel.reopen'),
      description: t('tasks.actions.uncomplete.confirmDescription'),
      confirmLabel: t('tasks.actions.uncomplete.confirm'),
    })
    if (!confirmed) {
      return
    }
    setBusyId(subtask.id)
    try {
      await uncompleteTask(subtask.id)
      toast.success(t('tasks.actions.uncomplete.success'))
      await invalidateParent()
    } catch {
      toast.error(t('tasks.actions.errors.generic'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(subtask: TaskSubtask) {
    const confirmed = await confirm({
      title: t('tasks.detail.subtaskPanel.deleteConfirmTitle'),
      description: t('tasks.detail.subtaskPanel.deleteConfirmDescription'),
      confirmLabel: t('tasks.detail.subtaskPanel.delete'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    setBusyId(subtask.id)
    try {
      await deleteTask(subtask.id)
      toast.success(t('tasks.detail.subtaskPanel.deleteSuccess'))
      await invalidateParent()
    } catch {
      toast.error(t('tasks.detail.subtaskPanel.deleteError'))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <RecordSection
      title={t('tasks.detail.sections.subtasks')}
      icon={<ListTree />}
      full
      className={className}
      action={
        canCreateSubtask ? (
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            {t('tasks.detail.createSubtask')}
          </Button>
        ) : null
      }
    >
      {subtasks.length > 0 ? (
        <SortableList
          items={items}
          onReorder={handleReorder}
          isPinned={() => !canReorder}
          dragHandleLabel={t('tasks.detail.subtaskPanel.reorderHandle')}
          pinnedRowClassName="bg-surface"
          renderItem={(item) => (
            <TaskSubtaskRow
              subtask={item.subtask}
              onOpen={onOpen}
              onComplete={(subtask) => setCompletingId(subtask.id)}
              onReopen={(subtask) => void handleReopen(subtask)}
              onDelete={(subtask) => void handleDelete(subtask)}
              isBusy={busyId === item.subtask.id || (completingId === item.subtask.id && completingLoading)}
            />
          )}
        />
      ) : (
        <p className="text-xs text-muted-foreground">{t('tasks.detail.subtasksEmpty')}</p>
      )}

      {completingTask ? (
        <TaskCompleteDialog
          open={completingId !== null}
          onOpenChange={(open) => {
            if (!open) {
              setCompletingId(null)
            }
          }}
          task={completingTask}
          // Spec 0155 D-6: the sub-task panel never completes for every assignee.
          forAllAssignees={false}
          onCompleted={() => void invalidateParent()}
        />
      ) : null}
    </RecordSection>
  )
}
