/**
 * Pure reducer applying a drag-and-drop move (`MoveBoardTaskPayload`) to the
 * board's own flat task list, for the optimistic update `use-task-board-
 * mutations.ts` writes into the query cache before the request even lands
 * (AC-027).
 */

import type { BoardTask, MoveBoardTaskPayload } from '@/features/work-orders/task-board/types'

function rootsInGroup(tasks: BoardTask[], stageId: number | null): BoardTask[] {
  return tasks
    .filter((task) => task.parent_task_id === null && task.work_order_stage_id === stageId)
    .sort((a, b) => a.stage_position - b.stage_position)
}

/**
 * Moves `move.task_id` to `move.work_order_stage_id` at `move.position`,
 * mirroring the backend's own semantics (AC-013): `position` is the index
 * inside the DESTINATION group after the task leaves its origin one. Both
 * the origin and destination groups come back with compact `0..n-1`
 * positions; sub-tasks and every other group are untouched. A task that is
 * not a known root is returned unchanged (defensive: the server is the
 * authority, this only drives the optimistic render).
 */
export function applyBoardMove(tasks: BoardTask[], move: MoveBoardTaskPayload): BoardTask[] {
  const movingTask = tasks.find((task) => task.id === move.task_id)
  if (!movingTask || movingTask.parent_task_id !== null) {
    return tasks
  }

  // Step 1: the origin group's siblings, without the moving task
  const originStageId = movingTask.work_order_stage_id
  const destinationStageId = move.work_order_stage_id
  const isSameGroup = originStageId === destinationStageId
  const originSiblings = rootsInGroup(tasks, originStageId).filter((task) => task.id !== move.task_id)

  // Step 2: insert the moving task into the destination group at the requested (clamped) index
  const destinationSiblings = isSameGroup ? originSiblings : rootsInGroup(tasks, destinationStageId)
  const insertIndex = Math.min(Math.max(move.position, 0), destinationSiblings.length)
  const reorderedDestination = [...destinationSiblings]
  reorderedDestination.splice(insertIndex, 0, movingTask)

  // Step 3: compact positions 0..n-1 for both affected groups
  const nextPositionById = new Map<number, number>()
  reorderedDestination.forEach((task, index) => nextPositionById.set(task.id, index))
  if (!isSameGroup) {
    originSiblings.forEach((task, index) => nextPositionById.set(task.id, index))
  }

  // Step 4: rewrite only the affected rows, leaving every other task (other groups, sub-tasks) untouched
  return tasks.map((task) => {
    const nextPosition = nextPositionById.get(task.id)
    if (nextPosition === undefined) {
      return task
    }
    const nextStageId = task.id === move.task_id ? destinationStageId : task.work_order_stage_id
    return { ...task, work_order_stage_id: nextStageId, stage_position: nextPosition }
  })
}
