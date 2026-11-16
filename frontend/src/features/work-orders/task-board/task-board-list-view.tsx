/**
 * Lista raggruppata (D-5, AC-026): one accent-striped `TaskBoardStageGroup`
 * per fase in `sort_order`, plus a trailing "Senza fase" — the SAME
 * component, just non-draggable. Owns the single `DndContext` for this view:
 * fase headers reorder among themselves, rows drag within/between groups.
 */

import { CSS } from '@dnd-kit/utilities'
import { DndContext, DragOverlay, closestCenter } from '@dnd-kit/core'
import { useSortable, SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
import {
  TASK_BOARD_LEADING_CLASS,
  TASK_BOARD_ROW_CLASS,
  TaskBoardCellEditContext,
} from '@/features/work-orders/task-board/task-board-row-columns'
import { TaskBoardStageGroup } from '@/features/work-orders/task-board/task-board-stage-group'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'
import { NO_STAGE_ACCENT, stageAccentAt, type StageAccent } from '@/features/work-orders/task-board/task-board-stage-accent'
import { stageDraggableId, toDndGroups, useTaskBoardDnd } from '@/features/work-orders/task-board/use-task-board-dnd'
import { useTaskBoardCellEdit } from '@/features/work-orders/task-board/use-task-board-cell-edit'
import { cn } from '@/lib/utils'


interface TaskBoardListViewProps {
  workOrderId: number
  groups: BoardStageGroup[]
  /** The SAME grouping, D-6 filters aside — `use-task-board-dnd.ts` needs it to compute a correct `position` when a filter hides siblings. */
  allGroups: BoardStageGroup[]
  today: string
  isReadOnly: boolean
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onSetSelection: (taskIds: number[], isSelected: boolean) => void
  onOpenTask: (taskId: number) => void
  onAddTask: (stageId: number | null) => void
  /** The commessa's `unstaged_logged_minutes` (spec 0163 AC-007), read only by the "Senza fase" group. */
  unstagedLoggedMinutes: number
}

export function TaskBoardListView({
  workOrderId,
  groups,
  allGroups,
  today,
  isReadOnly,
  selectedTaskIds,
  onToggleSelection,
  onSetSelection,
  onOpenTask,
  onAddTask,
  unstagedLoggedMinutes,
}: TaskBoardListViewProps) {
  const stages = groups.filter((group) => group.stage !== null).map((group) => group.stage!)
  const visibleDndGroups = toDndGroups(groups)
  const allDndGroups = toDndGroups(allGroups)
  const tasksById = new Map(allGroups.flatMap((group) => group.roots.map((node) => [node.task.id, node.task] as const)))

  const { cellEdit, dialogSlot } = useTaskBoardCellEdit(workOrderId)

  const { sensors, activeTask, activeStage, overGroupKey, handleDragStart, handleDragOver, handleDragEnd } =
    useTaskBoardDnd({
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
      <TaskBoardCellEditContext.Provider value={cellEdit}>
      <SortableContext items={stages.map((stage) => stageDraggableId(stage.id))} strategy={verticalListSortingStrategy}>
        <ul className="flex flex-col gap-6">
          {groups.map((group, index) =>
            group.stage ? (
              <SortableStageGroupItem
                key={group.stage.id}
                workOrderId={workOrderId}
                group={group}
                accent={stageAccentAt(index)}
                isReadOnly={isReadOnly}
                isDragOver={overGroupKey === String(group.stage.id)}
                today={today}
                selectedTaskIds={selectedTaskIds}
                onToggleSelection={onToggleSelection}
                onSetSelection={onSetSelection}
                onOpenTask={onOpenTask}
                onAddTask={onAddTask}
              />
            ) : (
              <TaskBoardStageGroup
                key="none"
                workOrderId={workOrderId}
                group={group}
                accent={NO_STAGE_ACCENT}
                isReadOnly={isReadOnly}
                isDragOver={overGroupKey === 'none'}
                today={today}
                selectedTaskIds={selectedTaskIds}
                onToggleSelection={onToggleSelection}
                onSetSelection={onSetSelection}
                onOpenTask={onOpenTask}
                onAddTask={onAddTask}
                unstagedLoggedMinutes={unstagedLoggedMinutes}
              />
            ),
          )}
        </ul>
      </SortableContext>
      </TaskBoardCellEditContext.Provider>

      <DragOverlay>
        {activeStage ? (
          <div className="flex items-center gap-2 rounded-lg bg-card px-3 py-2 opacity-95 shadow-md ring-1 ring-border">
            <GripVertical className="size-3.5 text-muted-foreground" aria-hidden="true" />
            <span className="text-sm font-semibold">{activeStage.name}</span>
          </div>
        ) : null}
        {activeTask ? (
          // Outside the cell-edit provider: the dragged copy is read-only.
          <div className={cn(TASK_BOARD_ROW_CLASS, 'rounded-lg bg-card shadow-lg ring-1 ring-border')}>
            <span className={TASK_BOARD_LEADING_CLASS} aria-hidden="true" />
            <TaskBoardRowContent task={activeTask} today={today} onOpen={() => undefined} />
          </div>
        ) : null}
      </DragOverlay>

      {dialogSlot}
    </DndContext>
  )
}

interface SortableStageGroupItemProps {
  workOrderId: number
  group: BoardStageGroup
  accent: StageAccent
  isReadOnly: boolean
  isDragOver: boolean
  today: string
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onSetSelection: (taskIds: number[], isSelected: boolean) => void
  onOpenTask: (taskId: number) => void
  onAddTask: (stageId: number | null) => void
}

/** Wires the fase header's own drag handle around `TaskBoardStageGroup`, mirroring `SortableRow` in `components/ui/sortable-list.tsx`. */
function SortableStageGroupItem({ group, isReadOnly, ...props }: SortableStageGroupItemProps) {
  const { t } = useTranslation()
  const stageId = group.stage!.id
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: stageDraggableId(stageId),
    data: { type: 'stage' },
    disabled: isReadOnly,
  })

  return (
    <TaskBoardStageGroup
      group={group}
      isReadOnly={isReadOnly}
      {...props}
      sortableRef={setNodeRef}
      sortableStyle={{ transform: CSS.Transform.toString(transform), transition }}
      isDraggingStage={isDragging}
      dragHandle={
        isReadOnly ? undefined : (
          <button
            type="button"
            ref={setActivatorNodeRef}
            aria-label={t('workOrders.taskBoard.stage.dragHandleLabel')}
            className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing"
            {...attributes}
            {...listeners}
          >
            <GripVertical className="size-3.5" aria-hidden="true" />
          </button>
        )
      }
    />
  )
}
