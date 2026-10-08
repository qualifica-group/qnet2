import { describe, expect, it } from 'vitest'
import { isSubtaskDone, subtaskProgress } from '@/features/tasks/task-subtask-progress'
import { taskStatus, taskSubtask } from '@/features/tasks/task-fixtures'

const CLOSED = taskStatus({ group: 'closed_positive' })

describe('subtaskProgress', () => {
  it('is all zero for no children', () => {
    expect(subtaskProgress([])).toEqual({ done: 0, total: 0, average: 0 })
  })

  it('counts the children closed positively and rounds their mean completion', () => {
    const subtasks = [
      taskSubtask({ completion_percentage: 100, task_status: CLOSED }),
      taskSubtask({ id: 102, completion_percentage: 10 }),
      taskSubtask({ id: 103, completion_percentage: 0 }),
    ]

    expect(subtaskProgress(subtasks)).toEqual({ done: 1, total: 3, average: 37 })
  })
})

// REQUIREMENT CHANGED (user directive 2026-10-06): "done" follows the status
// phase, as the grid's title cell does, no longer the 100% percentage.
describe('isSubtaskDone', () => {
  it('is true only for the positive closure phase', () => {
    expect(isSubtaskDone(taskSubtask({ completion_percentage: 100 }))).toBe(false)
    expect(isSubtaskDone(taskSubtask({ task_status: taskStatus({ group: 'closed_negative' }) }))).toBe(false)
    expect(isSubtaskDone(taskSubtask({ task_status: CLOSED }))).toBe(true)
  })
})
