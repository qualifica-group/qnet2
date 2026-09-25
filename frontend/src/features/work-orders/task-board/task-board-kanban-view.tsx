/**
 * Board kanban (D-5): one column per fase (`sort_order`) plus "Senza fase",
 * scrolling horizontally INSIDE its own container (never the page, per
 * `ui-design.md §3`). Shares the exact move semantics of the list view
 * through the same `use-task-board-dnd.ts` engine — only the container
 * shape differs.
 */

import { DndContext, DragOverlay, closestCenter } from '@dnd-kit/core'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskBoardEndDate } from '@/features/work-orders/task-board/task-board-end-date'
import { TaskBoardKanbanColumn } from '@/features/work-orders/task-board/task-board-kanban-column'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'
import { NO_STAGE_ACCENT, stageAccentAt } from '@/features/work-orders/task-board/task-board-stage-accent'
import { toDndGroups, useTaskBoardDnd } from '@/features/work-orders/task-board/use-task-board-dnd'


interface TaskBoardKanbanViewProps {
  workOrderId: number
  groups: BoardStageGroup[]
  /** The SAME grouping, D-6 filters aside — `use-task-board-dnd.ts` needs it to compute a correct `position` when a filter hides siblings. */
  allGroups: BoardStageGroup[]
  today: string
  isReadOnly: boolean
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
  onAddTask: (stageId: number | null) => void
  /** The commessa's `unstaged_logged_minutes` (spec 0163 AC-007), read only by the "Senza fase" column. */
  unstagedLoggedMinutes: number
}

export function TaskBoardKanbanView({
  workOrderId,
  groups,
  allGroups,
  today,
  isReadOnly,
  selectedTaskIds,
  onToggleSelection,
  onOpenTask,
  onAddTask,
  unstagedLoggedMinutes,
}: TaskBoardKanbanViewProps) {
  const stages = groups.filter((group) => group.stage !== null).map((group) => group.stage!)
  const visibleDndGroups = toDndGroups(groups)
  const allDndGroups = toDndGroups(allGroups)
  const tasksById = new Map(allGroups.flatMap((group) => group.roots.map((node) => [node.task.id, node.task] as const)))

  const { sensors, activeTask, overGroupKey, handleDragStart, handleDragOver, handleDragEnd } = useTaskBoardDnd({
    workOrderId,
    stages,
    visibleGroups: visibleDndGroups,
    allGroups: allDndGroups,
    tasksById,
    isReadOnly,
  })

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
    >
      <div className="flex gap-3 overflow-x-auto pb-2">
        {groups.map((group, index) => (
          <TaskBoardKanbanColumn
            key={group.stage?.id ?? 'none'}
            group={group}
            accent={group.stage ? stageAccentAt(index) : NO_STAGE_ACCENT}
            isReadOnly={isReadOnly}
            isDragOver={overGroupKey === (group.stage ? String(group.stage.id) : 'none')}
            today={today}
            selectedTaskIds={selectedTaskIds}
            onToggleSelection={onToggleSelection}
            onOpenTask={onOpenTask}
            onAddTask={onAddTask}
            unstagedLoggedMinutes={group.stage === null ? unstagedLoggedMinutes : undefined}
          />
        ))}
      </div>

      <DragOverlay>
        {activeTask ? (
          <div className="flex w-72 flex-col gap-1.5 rotate-2 rounded-lg bg-card p-3 opacity-95 shadow-lg ring-1 ring-border">
            <span className="truncate text-sm font-semibold text-foreground">{activeTask.title}</span>
            <div className="flex flex-wrap items-center gap-1.5">
              <TaskLookupBadge value={activeTask.task_priority} />
              <TaskBoardEndDate task={activeTask} today={today} />
            </div>
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  )
}
