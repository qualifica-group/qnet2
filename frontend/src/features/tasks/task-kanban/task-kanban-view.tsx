/**
 * The /tasks Kanban (spec 0157 D-2..D-5): "per stato" or "per scadenza",
 * chosen by the caller (`TaskViewModeSelector`). Owns its own data slice
 * (`useTaskKanbanRows`) and drag engine (`useTaskKanbanDnd`); the two board
 * variants only differ in how their columns/moves are built.
 */
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { DndContext, DragOverlay, closestCenter } from '@dnd-kit/core'
import { AlertTriangle } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { useAbilities } from '@/features/auth/use-abilities'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskKanbanColumn } from '@/features/tasks/task-kanban/task-kanban-column'
import { TaskKanbanDueChip } from '@/features/tasks/task-kanban/task-kanban-due-chip'
import { TaskKanbanToolbar } from '@/features/tasks/task-kanban/task-kanban-toolbar'
import { buildTaskDueKanbanGroups } from '@/features/tasks/task-kanban/task-kanban-due-columns'
import { dueBucketDropDate } from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import { buildTaskStatusKanbanGroups, isManualStatusColumn } from '@/features/tasks/task-kanban/task-kanban-status-columns'
import { useTaskKanbanDnd } from '@/features/tasks/task-kanban/use-task-kanban-dnd'
import { useTaskKanbanDueMove } from '@/features/tasks/task-kanban/use-task-kanban-due-move'
import { TASK_KANBAN_ROW_LIMIT, useTaskKanbanRows } from '@/features/tasks/task-kanban/use-task-kanban-rows'
import { useTaskKanbanStatuses } from '@/features/tasks/task-kanban/use-task-kanban-statuses'
import { useTaskKanbanStatusMove } from '@/features/tasks/task-kanban/use-task-kanban-status-move'
import type { DueBucketKey } from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanGroup } from '@/features/tasks/task-kanban/task-kanban-types'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'
import type { TaskKanbanMode } from '@/features/tasks/use-task-kanban-mode'
import type { ModuleCreateParams } from '@/features/modules/types'

/** Today as `YYYY-MM-DD` in the ACTOR's own local calendar day (mirrors `use-task-form.ts`'s own helper). */
function todayIsoDate(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

interface TaskKanbanViewProps {
  mode: TaskKanbanMode
  onOpenTask: (id: number) => void
  onCreateTask: (params: ModuleCreateParams) => void
}

export function TaskKanbanView({ mode, onOpenTask, onCreateTask }: TaskKanbanViewProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const canCreate = can('tasks.create')
  const today = useMemo(() => todayIsoDate(), [])

  const data = useTaskKanbanRows()
  const { statuses } = useTaskKanbanStatuses()

  const statusMove = useTaskKanbanStatusMove({ onMutated: data.refresh })
  const dueMove = useTaskKanbanDueMove({ today, onMutated: data.refresh })

  const statusGroups = useMemo(
    () => buildTaskStatusKanbanGroups(statuses, data.rows),
    [statuses, data.rows],
  )
  const dueGroups = useMemo(
    () => buildTaskDueKanbanGroups(data.rows, today, t),
    [data.rows, today, t],
  )
  // Widened to `TaskKanbanGroup<string>[]`: `useTaskKanbanDnd` and `TaskKanbanColumn`
  // treat the key as an opaque string either way, so the board's own
  // (`string`/`DueBucketKey`) literal type is only load-bearing inside
  // `task-kanban-status-columns.ts`/`task-kanban-due-columns.ts` themselves.
  const groups: TaskKanbanGroup<string>[] = mode === 'status' ? statusGroups : dueGroups

  const { sensors, activeRow, handleDragStart, handleDragEnd } = useTaskKanbanDnd({
    groups,
    onDrop: (row, targetKey) => {
      if (mode === 'status') {
        const targetStatus = statuses.find((status) => String(status.id) === targetKey)
        if (targetStatus) {
          statusMove.moveToStatus(row, targetStatus.id, targetStatus.meta.group)
        }
        return
      }
      dueMove.moveToBucket(row, targetKey as DueBucketKey)
    },
  })

  if (data.isPending) {
    return (
      <div className="flex gap-3">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-64 w-72 shrink-0" />
        ))}
      </div>
    )
  }

  if (data.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('tasks.views.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={() => void data.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <TaskKanbanToolbar data={data} />

      {data.exceededLimit ? (
        <p className="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">
          <AlertTriangle aria-hidden="true" className="size-3.5 shrink-0" />
          {t('tasks.views.kanbanLimitExceeded', { total: data.total, limit: TASK_KANBAN_ROW_LIMIT })}
        </p>
      ) : null}

      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
      >
        <div className="flex gap-3 overflow-x-auto pb-2">
          {groups.map((group) => (
            <TaskKanbanColumn
              key={group.key}
              group={group}
              today={today}
              onOpenTask={onOpenTask}
              onAddTask={
                canCreate
                  ? buildAddTaskHandler(mode, group.key, statuses, today, onCreateTask)
                  : undefined
              }
            />
          ))}
        </div>

        <DragOverlay>
          {activeRow ? (
            <div className="flex w-72 flex-col gap-1.5 rotate-2 rounded-lg bg-card p-3 opacity-95 shadow-lg ring-1 ring-border">
              <span className="truncate text-sm font-semibold text-foreground">{activeRow.title}</span>
              <div className="flex flex-wrap items-center gap-1.5">
                <TaskLookupBadge value={activeRow.task_priority} />
                <TaskKanbanDueChip row={activeRow} today={today} />
              </div>
            </div>
          ) : null}
        </DragOverlay>
      </DndContext>

      {statusMove.completingTask ? (
        <TaskCompleteDialog
          open
          onOpenChange={(open) => {
            if (!open) {
              statusMove.closeCompleteDialog()
            }
          }}
          task={statusMove.completingTask}
          forAllAssignees
          onCompleted={statusMove.handleCompleted}
        />
      ) : null}
    </div>
  )
}

/**
 * The column's own "+" handler (spec 0157 D-4), pre-filling the SAME status
 * or due date the column represents — `null` when this column never offers
 * "+" at all: a non-manual status column, or a due bucket with no drop date
 * (Scaduti/Completati, `dueBucketDropDate` returning `null` for both).
 */
function buildAddTaskHandler(
  mode: TaskKanbanMode,
  key: string,
  statuses: TaskStatusForSelectItem[],
  today: string,
  onCreateTask: (params: ModuleCreateParams) => void,
): (() => void) | undefined {
  if (mode === 'due') {
    const endDate = dueBucketDropDate(key as DueBucketKey, today)
    return endDate === null ? undefined : () => onCreateTask({ end_date: endDate })
  }
  const status = statuses.find((candidate) => String(candidate.id) === key)
  return status && isManualStatusColumn(status.meta.group)
    ? () => onCreateTask({ task_status_id: status.id })
    : undefined
}
