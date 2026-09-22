import { describe, expect, it } from 'vitest'
import { boardTask } from '@/features/work-orders/task-board/task-board-fixtures'
import { applyBoardMove } from '@/features/work-orders/task-board/task-board-move'

describe('applyBoardMove (AC-013)', () => {
  it('reorders within the same fase, compacting positions 0..n-1', () => {
    const tasks = [
      boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 }),
      boardTask({ id: 2, work_order_stage_id: 1, stage_position: 1 }),
      boardTask({ id: 3, work_order_stage_id: 1, stage_position: 2 }),
    ]

    const result = applyBoardMove(tasks, { task_id: 1, work_order_stage_id: 1, position: 2 })

    expect(result.find((t) => t.id === 1)).toMatchObject({ work_order_stage_id: 1, stage_position: 2 })
    expect(result.find((t) => t.id === 2)).toMatchObject({ work_order_stage_id: 1, stage_position: 0 })
    expect(result.find((t) => t.id === 3)).toMatchObject({ work_order_stage_id: 1, stage_position: 1 })
  })

  it('moves across fasi, compacting BOTH the origin and destination groups', () => {
    const tasks = [
      boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 }),
      boardTask({ id: 2, work_order_stage_id: 1, stage_position: 1 }),
      boardTask({ id: 3, work_order_stage_id: 2, stage_position: 0 }),
    ]

    const result = applyBoardMove(tasks, { task_id: 1, work_order_stage_id: 2, position: 0 })

    expect(result.find((t) => t.id === 1)).toMatchObject({ work_order_stage_id: 2, stage_position: 0 })
    expect(result.find((t) => t.id === 3)).toMatchObject({ work_order_stage_id: 2, stage_position: 1 })
    expect(result.find((t) => t.id === 2)).toMatchObject({ work_order_stage_id: 1, stage_position: 0 })
  })

  it('moves a root into "Senza fase" (work_order_stage_id: null)', () => {
    const tasks = [boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 })]

    const result = applyBoardMove(tasks, { task_id: 1, work_order_stage_id: null, position: 0 })

    expect(result.find((t) => t.id === 1)).toMatchObject({ work_order_stage_id: null, stage_position: 0 })
  })

  it('leaves sub-tasks and unrelated groups untouched', () => {
    const child = boardTask({ id: 9, parent_task_id: 1, work_order_stage_id: null, stage_position: 0 })
    const unrelatedRoot = boardTask({ id: 8, work_order_stage_id: 3, stage_position: 0 })
    const tasks = [
      boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 }),
      child,
      unrelatedRoot,
    ]

    const result = applyBoardMove(tasks, { task_id: 1, work_order_stage_id: 2, position: 0 })

    expect(result.find((t) => t.id === 9)).toEqual(child)
    expect(result.find((t) => t.id === 8)).toEqual(unrelatedRoot)
  })

  it('clamps an out-of-range position to the end of the destination group', () => {
    const tasks = [
      boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 }),
      boardTask({ id: 2, work_order_stage_id: 2, stage_position: 0 }),
    ]

    const result = applyBoardMove(tasks, { task_id: 1, work_order_stage_id: 2, position: 99 })

    expect(result.find((t) => t.id === 1)).toMatchObject({ work_order_stage_id: 2, stage_position: 1 })
  })

  it('is a no-op for an unknown task id or a sub-task (only roots carry a fase, D-3)', () => {
    const tasks = [
      boardTask({ id: 1, work_order_stage_id: 1, stage_position: 0 }),
      boardTask({ id: 2, parent_task_id: 1, work_order_stage_id: null, stage_position: 0 }),
    ]

    expect(applyBoardMove(tasks, { task_id: 999, work_order_stage_id: 1, position: 0 })).toEqual(tasks)
    expect(applyBoardMove(tasks, { task_id: 2, work_order_stage_id: 1, position: 0 })).toEqual(tasks)
  })
})
