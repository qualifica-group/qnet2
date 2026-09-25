/**
 * The /tasks Kanban (spec 0157 D-2..D-5, spec 0164 D-1..D-3): "per stato" or
 * "per scadenza", chosen by the caller (`TaskViewModeSelector`). Owns the
 * board's shared filter slice (`useTaskKanbanFilters`) and drag engine
 * (`useTaskKanbanDnd`); each column loads its OWN rows
 * (`TaskKanbanColumn`/`useTaskKanbanColumnRows`) — the two board variants
 * only differ in how their column METADATA (`TaskKanbanGroup[]`, no rows) is
 * built.
 */
import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DndContext, DragOverlay, closestCenter } from '@dnd-kit/core'
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
import { taskKanbanColumnQueryKey } from '@/features/tasks/task-kanban/use-task-kanban-column-rows'
import { useTaskKanbanDnd } from '@/features/tasks/task-kanban/use-task-kanban-dnd'
import { useTaskKanbanDueMove } from '@/features/tasks/task-kanban/use-task-kanban-due-move'
import { useTaskKanbanFilters } from '@/features/tasks/task-kanban/use-task-kanban-filters'
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
  const queryClient = useQueryClient()

  const filters = useTaskKanbanFilters()
  const { statuses } = useTaskKanbanStatuses()

  const statusMove = useTaskKanbanStatusMove()
  const dueMove = useTaskKanbanDueMove({ today })

  const statusGroups = useMemo(() => buildTaskStatusKanbanGroups(statuses), [statuses])
  const dueGroups = useMemo(() => buildTaskDueKanbanGroups(t), [t])
  // Widened to `TaskKanbanGroup<string>[]`: `useTaskKanbanDnd` and
  // `TaskKanbanColumn` treat the key as an opaque string either way, so the
  // board's own (`string`/`DueBucketKey`) literal type is only load-bearing
  // inside `task-kanban-status-columns.ts`/`task-kanban-due-columns.ts`.
  const groups: TaskKanbanGroup<string>[] = mode === 'status' ? statusGroups : dueGroups

  const groupsByKey = useMemo(
    () => new Map(groups.map((group) => [String(group.key), group] as const)),
    [groups],
  )

  // Spec 0164 D-3: a successful move reloads ONLY the origin/destination
  // columns — never the other columns' own already-loaded blocks.
  const invalidateColumns = useCallback(
    (...keys: string[]) => {
      for (const key of keys) {
        const kanbanGroup = groupsByKey.get(key)?.kanbanGroup
        if (kanbanGroup) {
          void queryClient.invalidateQueries({ queryKey: taskKanbanColumnQueryKey(kanbanGroup) })
        }
      }
    },
    [groupsByKey, queryClient],
  )

  const { sensors, activeRow, handleDragStart, handleDragEnd } = useTaskKanbanDnd({
    groups,
    onDrop: (row, originKey, targetKey) => {
      const onMutated = () => invalidateColumns(originKey, targetKey)
      if (mode === 'status') {
        const targetStatus = statuses.find((status) => String(status.id) === targetKey)
        if (targetStatus) {
          statusMove.moveToStatus(row, targetStatus.id, targetStatus.meta.group, onMutated)
        }
        return
      }
      dueMove.moveToBucket(row, targetKey as DueBucketKey, onMutated)
    },
  })

  if (filters.isPending) {
    return (
      <div className="flex gap-3">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-64 w-72 shrink-0" />
        ))}
      </div>
    )
  }

  if (filters.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('tasks.views.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={() => void filters.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <TaskKanbanToolbar data={filters} />

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
              filters={filters}
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
