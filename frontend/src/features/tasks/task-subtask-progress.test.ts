import { describe, expect, it } from 'vitest'
import { isSubtaskDone, subtaskProgress } from '@/features/tasks/task-subtask-progress'
import { taskSubtask } from '@/features/tasks/task-fixtures'

describe('subtaskProgress', () => {
  it('is all zero for no children', () => {
    expect(subtaskProgress([])).toEqual({ done: 0, total: 0, average: 0 })
  })

  it('counts the children at 100% and rounds their mean completion', () => {
    const subtasks = [
      taskSubtask({ completion_percentage: 100 }),
      taskSubtask({ id: 102, completion_percentage: 10 }),
      taskSubtask({ id: 103, completion_percentage: 0 }),
    ]

    expect(subtaskProgress(subtasks)).toEqual({ done: 1, total: 3, average: 37 })
  })
})

describe('isSubtaskDone', () => {
  it('is true only at 100%', () => {
    expect(isSubtaskDone(taskSubtask({ completion_percentage: 99 }))).toBe(false)
    expect(isSubtaskDone(taskSubtask({ completion_percentage: 100 }))).toBe(true)
  })
})
