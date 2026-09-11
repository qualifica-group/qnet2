import { describe, expect, it } from 'vitest'
import { taskActionAvailability } from '@/features/tasks/task-action-availability'
import { taskDetail, taskStatus } from '@/features/tasks/task-fixtures'

describe('taskActionAvailability — open phase, not blocked (AC-038)', () => {
  it('offers complete and block, nothing else', () => {
    const task = taskDetail({ task_status: taskStatus({ group: 'open' }), is_blocked: false })

    expect(taskActionAvailability(task)).toEqual({
      complete: true,
      uncomplete: false,
      approve: false,
      reject: false,
      block: true,
      unblock: false,
    })
  })
})

describe('taskActionAvailability — pending phase (still working, not terminal)', () => {
  it('behaves like open: completable and blockable', () => {
    const task = taskDetail({ task_status: taskStatus({ group: 'pending' }), is_blocked: false })

    expect(taskActionAvailability(task).complete).toBe(true)
    expect(taskActionAvailability(task).uncomplete).toBe(false)
  })
})

describe('taskActionAvailability — in_validation phase', () => {
  it('offers approve/reject/uncomplete, not complete', () => {
    const task = taskDetail({ task_status: taskStatus({ group: 'in_validation' }), is_blocked: false })

    expect(taskActionAvailability(task)).toEqual({
      complete: false,
      uncomplete: true,
      approve: true,
      reject: true,
      block: true,
      unblock: false,
    })
  })
})

describe.each(['closed_positive', 'closed_negative'] as const)(
  'taskActionAvailability — closing phase "%s" (D-7 both closures are terminal)',
  (group) => {
    it('offers only uncomplete/block', () => {
      const task = taskDetail({ task_status: taskStatus({ group }), is_blocked: false })

      expect(taskActionAvailability(task)).toEqual({
        complete: false,
        uncomplete: true,
        approve: false,
        reject: false,
        block: true,
        unblock: false,
      })
    })
  },
)

describe('taskActionAvailability — is_blocked (D-8: a blocked task is suspended)', () => {
  it('suspends every action but unblock, whatever the phase', () => {
    const task = taskDetail({ task_status: taskStatus({ group: 'in_validation' }), is_blocked: true })

    expect(taskActionAvailability(task)).toEqual({
      complete: false,
      uncomplete: false,
      approve: false,
      reject: false,
      block: false,
      unblock: true,
    })
  })
})
