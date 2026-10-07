import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ListTree, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CompletionBar } from '@/components/completion-bar'
import { RecordSection } from '@/components/detail/record-panel'
import { SortableList, type SortableListItem } from '@/components/ui/sortable-list'
import { useConfirm } from '@/components/confirm-dialog-context'
import { tintClassFor } from '@/features/custom-fields/badge-color-tokens'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { deleteTask, fetchTask, taskDetailQueryKey, uncompleteTask } from '@/features/tasks/api'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { TaskSubtaskRow } from '@/features/tasks/task-subtask-row'
import { subtaskProgress } from '@/features/tasks/task-subtask-progress'
import { useReorderTaskSubtasks } from '@/features/tasks/use-task-mutations'
import type { TaskSubtask } from '@/features/tasks/types'

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

/** The panel's header band: "2 di 5 completati" and the children's mean completion. */
function SubtaskProgressBand({ subtasks }: { subtasks: TaskSubtask[] }) {
  const { t } = useTranslation()
  const { done, total, average } = subtaskProgress(subtasks)

  return (
    <div className="flex flex-col gap-1.5 rounded-lg bg-muted/40 px-3 py-2 @md:flex-row @md:items-center @md:gap-4">
      <p className="shrink-0 text-xs text-muted-foreground">
        <span className="text-sm font-semibold tabular-nums text-foreground">{done}</span>{' '}
        {t('tasks.detail.subtaskPanel.doneOf', { count: total })}
      </p>
      <CompletionBar
        value={average}
        label={t('tasks.detail.subtaskPanel.overallProgress')}
        barClassName="flex-1"
        valueClassName="w-9 text-right"
        className="min-w-0 flex-1"
      />
    </div>
  )
}

/** No children yet: a dashed placeholder that says what the panel is for. */
function SubtasksEmptyState() {
  const { t } = useTranslation()

  return (
    <div className="flex items-center gap-3 rounded-lg border border-dashed p-3">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <ListTree className="size-4" aria-hidden="true" />
      </span>
      <div className="min-w-0">
        <p className="text-sm font-medium text-foreground">{t('tasks.detail.subtasksEmpty')}</p>
        <p className="text-xs text-muted-foreground">{t('tasks.detail.subtaskPanel.emptyHint')}</p>
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
          <Button type="button" size="sm" onClick={onCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            {t('tasks.detail.createSubtask')}
          </Button>
        ) : null
      }
    >
      {subtasks.length > 0 ? (
        <>
          <SubtaskProgressBand subtasks={subtasks} />
          <SortableList
            items={items}
            onReorder={handleReorder}
            isPinned={() => !canReorder}
            dragHandleLabel={t('tasks.detail.subtaskPanel.reorderHandle')}
            pinnedRowClassName="bg-surface"
            itemClassName={(item) => tintClassFor(item.subtask.task_type?.color)}
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
        </>
      ) : (
        <SubtasksEmptyState />
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
          onCompleted={() => void invalidateParent()}
        />
      ) : null}
    </RecordSection>
  )
}
