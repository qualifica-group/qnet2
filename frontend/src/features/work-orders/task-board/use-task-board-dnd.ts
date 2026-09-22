/**
 * Shared drag-and-drop engine for the Task board (D-5/AC-027): ONE `DndContext`
 * per view (list, kanban) routes both drag kinds through here — fase reorder
 * (list view's group headers) and task move (list groups AND kanban columns,
 * same semantics) — distinguished by `active.data.current.type`. The actual
 * network call/optimistic cache write is `useMoveBoardTask`/
 * `useReorderWorkOrderStages` (`use-task-board-mutations.ts`); this hook only
 * resolves WHAT to call from the drag event.
 */

import { useState } from 'react'
import {
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable'
import {
  useMoveBoardTask,
  useReorderWorkOrderStages,
} from '@/features/work-orders/task-board/use-task-board-mutations'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'
import type { BoardTask, WorkOrderStage } from '@/features/work-orders/task-board/types'

const STAGE_ID_PREFIX = 'stage-'

/** One draggable/droppable group of root tasks: a fase (`stageId` set) or "Senza fase" (`null`), in `taskIds` order. */
export interface DndGroup {
  key: string
  stageId: number | null
  taskIds: number[]
  isClosed: boolean
}

/** Derives the engine's own group shape from `groupRootsByStage`'s output, one call site for both views. */
export function toDndGroups(stageGroups: BoardStageGroup[]): DndGroup[] {
  return stageGroups.map((group) => ({
    key: group.stage ? String(group.stage.id) : 'none',
    stageId: group.stage?.id ?? null,
    taskIds: group.roots.map((node) => node.task.id),
    isClosed: group.stage?.closed_at != null,
  }))
}

/** The draggable id of a fase's header handle — distinct from a task's own numeric id string. */
export function stageDraggableId(stageId: number): string {
  return `${STAGE_ID_PREFIX}${stageId}`
}

/** The droppable id of a group's container (empty-space / below-last-row drop target). */
export function groupDroppableId(key: string): string {
  return `group-${key}`
}

function originGroupKey(task: BoardTask): string {
  return task.work_order_stage_id === null ? 'none' : String(task.work_order_stage_id)
}

/**
 * Resolves the destination group/index a drop lands on. `position` is ALWAYS
 * computed against `allGroups` (the board's full, unfiltered roots per fase)
 * — never against the filtered `visibleGroups` a D-6 filter renders: the
 * backend's `position` is the index among ALL of a fase's roots, and sending
 * an index counted only over the VISIBLE ones would desync every hidden
 * sibling's own position (a correctness bug, not a filter nicety).
 *
 * `visibleGroups` is consulted ONLY for the "dropped on empty space below the
 * rendered rows" case, to anchor after the last row the user could actually
 * see — its `allGroups` index is still what gets sent.
 */
function resolveDropTarget(
  overId: string,
  visibleGroups: DndGroup[],
  allGroups: DndGroup[],
): { key: string; index: number } | null {
  if (overId.startsWith('group-')) {
    const key = overId.slice('group-'.length)
    const allGroup = allGroups.find((candidate) => candidate.key === key)
    if (!allGroup) {
      return null
    }
    const visibleTaskIds = visibleGroups.find((candidate) => candidate.key === key)?.taskIds ?? []
    const lastVisibleId = visibleTaskIds[visibleTaskIds.length - 1]
    if (lastVisibleId === undefined) {
      // Nothing rendered in this group (filtered out or genuinely empty): append at the true end.
      return { key, index: allGroup.taskIds.length }
    }
    const anchorIndex = allGroup.taskIds.indexOf(lastVisibleId)
    return { key, index: anchorIndex === -1 ? allGroup.taskIds.length : anchorIndex + 1 }
  }
  const overTaskId = Number(overId)
  if (Number.isNaN(overTaskId)) {
    return null
  }
  const allGroup = allGroups.find((candidate) => candidate.taskIds.includes(overTaskId))
  return allGroup ? { key: allGroup.key, index: allGroup.taskIds.indexOf(overTaskId) } : null
}

interface UseTaskBoardDndOptions {
  workOrderId: number
  stages: WorkOrderStage[]
  /** The RENDERED groups (D-6 filters applied): drives what the user actually drags/hovers over. */
  visibleGroups: DndGroup[]
  /** The board's full groups, filters aside — see `resolveDropTarget`. */
  allGroups: DndGroup[]
  tasksById: Map<number, BoardTask>
  isReadOnly: boolean
}

/**
 * Sensors + drag handlers for a board view. `isReadOnly` is defense in depth
 * only (AC-029's real gate is that read-only rows/headers never render a
 * handle in the first place, so the pointer/keyboard sensor never activates).
 */
export function useTaskBoardDnd({
  workOrderId,
  stages,
  visibleGroups,
  allGroups,
  tasksById,
  isReadOnly,
}: UseTaskBoardDndOptions) {
  const moveTask = useMoveBoardTask(workOrderId)
  const reorderStages = useReorderWorkOrderStages(workOrderId)

  const [activeTaskId, setActiveTaskId] = useState<number | null>(null)
  const [activeStageId, setActiveStageId] = useState<number | null>(null)
  const [overGroupKey, setOverGroupKey] = useState<string | null>(null)

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  function handleDragStart(event: DragStartEvent) {
    if (event.active.data.current?.type === 'stage') {
      setActiveStageId(Number(String(event.active.id).slice(STAGE_ID_PREFIX.length)))
    } else {
      setActiveTaskId(Number(event.active.id))
    }
  }

  function handleDragOver(event: DragOverEvent) {
    const { active, over } = event
    if (!over || active.data.current?.type === 'stage') {
      setOverGroupKey(null)
      return
    }
    setOverGroupKey(resolveDropTarget(String(over.id), visibleGroups, allGroups)?.key ?? null)
  }

  function handleStageDragEnd(activeId: string, overId: string) {
    if (activeId === overId || !overId.startsWith(STAGE_ID_PREFIX)) {
      return
    }
    const ordered = [...stages].sort((a, b) => a.sort_order - b.sort_order)
    const oldIndex = ordered.findIndex((stage) => stageDraggableId(stage.id) === activeId)
    const newIndex = ordered.findIndex((stage) => stageDraggableId(stage.id) === overId)
    if (oldIndex === -1 || newIndex === -1 || oldIndex === newIndex) {
      return
    }
    reorderStages.mutate(arrayMove(ordered, oldIndex, newIndex).map((stage) => stage.id))
  }

  function handleTaskDragEnd(taskId: number, overId: string) {
    const task = tasksById.get(taskId)
    if (!task) {
      return
    }
    const target = resolveDropTarget(overId, visibleGroups, allGroups)
    const targetGroup = target ? allGroups.find((candidate) => candidate.key === target.key) : undefined
    if (!target || !targetGroup || targetGroup.isClosed) {
      return
    }
    const originKey = originGroupKey(task)
    const originIndex = allGroups.find((candidate) => candidate.key === originKey)?.taskIds.indexOf(taskId) ?? -1
    if (target.key === originKey && target.index === originIndex) {
      return
    }
    moveTask.mutate({ task_id: taskId, work_order_stage_id: targetGroup.stageId, position: target.index })
  }

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event
    setActiveTaskId(null)
    setActiveStageId(null)
    setOverGroupKey(null)
    if (isReadOnly || !over) {
      return
    }
    if (active.data.current?.type === 'stage') {
      handleStageDragEnd(String(active.id), String(over.id))
    } else {
      handleTaskDragEnd(Number(active.id), String(over.id))
    }
  }

  return {
    sensors,
    activeTask: activeTaskId !== null ? (tasksById.get(activeTaskId) ?? null) : null,
    activeStage: activeStageId !== null ? (stages.find((stage) => stage.id === activeStageId) ?? null) : null,
    overGroupKey,
    handleDragStart,
    handleDragOver,
    handleDragEnd,
  }
}
