import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import type { DragEndEvent } from '@dnd-kit/core'
import { boardTask, workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'
import { stageDraggableId, toDndGroups, useTaskBoardDnd } from '@/features/work-orders/task-board/use-task-board-dnd'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'

const moveMutateMock = vi.fn()
const reorderMutateMock = vi.fn()

vi.mock('@/features/work-orders/task-board/use-task-board-mutations', () => ({
  useMoveBoardTask: () => ({ mutate: moveMutateMock }),
  useReorderWorkOrderStages: () => ({ mutate: reorderMutateMock }),
}))

/** Builds the minimal `DragEndEvent` shape `use-task-board-dnd.ts` actually reads. */
function dragEndEvent(activeId: string, activeType: 'task' | 'stage', overId: string | null): DragEndEvent {
  return {
    active: { id: activeId, data: { current: { type: activeType } } },
    over: overId ? { id: overId, data: { current: {} } } : null,
  } as unknown as DragEndEvent
}

beforeEach(() => {
  moveMutateMock.mockReset()
  reorderMutateMock.mockReset()
})

describe('useTaskBoardDnd — task move (AC-027)', () => {
  it('calls move with the destination fase and index when dropped on another task', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })
    const stage2 = workOrderStage({ id: 2, sort_order: 1 })
    const taskA = boardTask({ id: 101, work_order_stage_id: 1, stage_position: 0 })
    const taskB = boardTask({ id: 102, work_order_stage_id: 2, stage_position: 0 })
    const groups: BoardStageGroup[] = [
      { stage: stage1, roots: [{ task: taskA, children: [] }] },
      { stage: stage2, roots: [{ task: taskB, children: [] }] },
    ]

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1, stage2],
        visibleGroups: toDndGroups(groups),
        allGroups: toDndGroups(groups),
        tasksById: new Map([
          [taskA.id, taskA],
          [taskB.id, taskB],
        ]),
        isReadOnly: false,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(String(taskA.id), 'task', String(taskB.id)))

    expect(moveMutateMock).toHaveBeenCalledWith({ task_id: 101, work_order_stage_id: 2, position: 0 })
  })

  it('appends to the end when dropped on an empty group container', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })
    const taskA = boardTask({ id: 101, work_order_stage_id: 1, stage_position: 0 })
    const groups: BoardStageGroup[] = [
      { stage: stage1, roots: [{ task: taskA, children: [] }] },
      { stage: null, roots: [] },
    ]

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1],
        visibleGroups: toDndGroups(groups),
        allGroups: toDndGroups(groups),
        tasksById: new Map([[taskA.id, taskA]]),
        isReadOnly: false,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(String(taskA.id), 'task', 'group-none'))

    expect(moveMutateMock).toHaveBeenCalledWith({ task_id: 101, work_order_stage_id: null, position: 0 })
  })

  it('never calls move when the destination fase is closed (D-4)', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0, closed_at: null })
    const closedStage = workOrderStage({ id: 2, sort_order: 1, closed_at: '2026-09-20T10:00:00Z' })
    const taskA = boardTask({ id: 101, work_order_stage_id: 1, stage_position: 0 })
    const groups: BoardStageGroup[] = [
      { stage: stage1, roots: [{ task: taskA, children: [] }] },
      { stage: closedStage, roots: [] },
    ]

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1, closedStage],
        visibleGroups: toDndGroups(groups),
        allGroups: toDndGroups(groups),
        tasksById: new Map([[taskA.id, taskA]]),
        isReadOnly: false,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(String(taskA.id), 'task', 'group-2'))

    expect(moveMutateMock).not.toHaveBeenCalled()
  })

  it('never calls move/reorder when the board is read-only (AC-029 defense in depth)', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })
    const taskA = boardTask({ id: 101, work_order_stage_id: 1, stage_position: 0 })
    const taskB = boardTask({ id: 102, work_order_stage_id: 1, stage_position: 1 })
    const groups: BoardStageGroup[] = [{ stage: stage1, roots: [{ task: taskA, children: [] }, { task: taskB, children: [] }] }]

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1],
        visibleGroups: toDndGroups(groups),
        allGroups: toDndGroups(groups),
        tasksById: new Map([
          [taskA.id, taskA],
          [taskB.id, taskB],
        ]),
        isReadOnly: true,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(String(taskA.id), 'task', String(taskB.id)))
    result.current.handleDragEnd(dragEndEvent(stageDraggableId(stage1.id), 'stage', stageDraggableId(stage1.id)))

    expect(moveMutateMock).not.toHaveBeenCalled()
    expect(reorderMutateMock).not.toHaveBeenCalled()
  })
})

/**
 * `position` must always be the index among ALL of a fase's roots, never
 * only the ones a D-6 filter currently renders — a filter hiding a sibling
 * must not desync that hidden task's own position (team-lead review finding).
 */
describe('useTaskBoardDnd — position under a client-side filter', () => {
  it('computes the index against the UNFILTERED group when a sibling is hidden', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })
    // Full board: [hidden, taskA, taskB] in that order. `hidden` fails the
    // active filter and never renders, so `visibleGroups` only has [A, B].
    const hidden = boardTask({ id: 100, work_order_stage_id: 1, stage_position: 0 })
    const taskA = boardTask({ id: 101, work_order_stage_id: 1, stage_position: 1 })
    const taskB = boardTask({ id: 102, work_order_stage_id: 1, stage_position: 2 })
    const allGroups: BoardStageGroup[] = [
      { stage: stage1, roots: [{ task: hidden, children: [] }, { task: taskA, children: [] }, { task: taskB, children: [] }] },
    ]
    const visibleGroups: BoardStageGroup[] = [{ stage: stage1, roots: [{ task: taskA, children: [] }, { task: taskB, children: [] }] }]

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1],
        visibleGroups: toDndGroups(visibleGroups),
        allGroups: toDndGroups(allGroups),
        tasksById: new Map([
          [hidden.id, hidden],
          [taskA.id, taskA],
          [taskB.id, taskB],
        ]),
        isReadOnly: false,
      }),
    )

    // Dropping taskB onto taskA (both visible): the true index of taskA
    // among ALL roots is 1 (hidden is at 0), NOT 0 (which a filtered-only
    // computation would have produced).
    result.current.handleDragEnd(dragEndEvent(String(taskB.id), 'task', String(taskA.id)))

    expect(moveMutateMock).toHaveBeenCalledWith({ task_id: 102, work_order_stage_id: 1, position: 1 })
  })

  it('anchors after the last VISIBLE row, at its true unfiltered index, when dropped on the group container', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })
    const stage2 = workOrderStage({ id: 2, sort_order: 1 })
    // Destination fase 2's full roots: [taskA, hidden] — `hidden` fails the
    // filter, so only taskA renders; dropping "below the list" still means
    // "after taskA", i.e. true index 1 (not 1 from an all-visible count of 1
    // either — the point is it is NOT `allGroups.taskIds.length` blindly).
    const moving = boardTask({ id: 200, work_order_stage_id: 1, stage_position: 0 })
    const taskA = boardTask({ id: 101, work_order_stage_id: 2, stage_position: 0 })
    const hidden = boardTask({ id: 102, work_order_stage_id: 2, stage_position: 1 })
    const allGroups: BoardStageGroup[] = [
      { stage: stage1, roots: [{ task: moving, children: [] }] },
      { stage: stage2, roots: [{ task: taskA, children: [] }, { task: hidden, children: [] }] },
    ]
    const visibleGroups: BoardStageGroup[] = [
      { stage: stage1, roots: [{ task: moving, children: [] }] },
      { stage: stage2, roots: [{ task: taskA, children: [] }] },
    ]

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1, stage2],
        visibleGroups: toDndGroups(visibleGroups),
        allGroups: toDndGroups(allGroups),
        tasksById: new Map([
          [moving.id, moving],
          [taskA.id, taskA],
          [hidden.id, hidden],
        ]),
        isReadOnly: false,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(String(moving.id), 'task', 'group-2'))

    expect(moveMutateMock).toHaveBeenCalledWith({ task_id: 200, work_order_stage_id: 2, position: 1 })
  })
})

describe('useTaskBoardDnd — fase reorder', () => {
  it('calls reorder with the full permutation of stage ids', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })
    const stage2 = workOrderStage({ id: 2, sort_order: 1 })
    const stage3 = workOrderStage({ id: 3, sort_order: 2 })

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1, stage2, stage3],
        visibleGroups: [],
        allGroups: [],
        tasksById: new Map(),
        isReadOnly: false,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(stageDraggableId(1), 'stage', stageDraggableId(3)))

    expect(reorderMutateMock).toHaveBeenCalledWith([2, 3, 1])
  })

  it('is a no-op when dropped on itself', () => {
    const stage1 = workOrderStage({ id: 1, sort_order: 0 })

    const { result } = renderHook(() =>
      useTaskBoardDnd({
        workOrderId: 9,
        stages: [stage1],
        visibleGroups: [],
        allGroups: [],
        tasksById: new Map(),
        isReadOnly: false,
      }),
    )

    result.current.handleDragEnd(dragEndEvent(stageDraggableId(1), 'stage', stageDraggableId(1)))

    expect(reorderMutateMock).not.toHaveBeenCalled()
  })
})
