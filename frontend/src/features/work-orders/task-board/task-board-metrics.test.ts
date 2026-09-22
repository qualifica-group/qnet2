import { describe, expect, it } from 'vitest'
import { boardTask } from '@/features/work-orders/task-board/task-board-fixtures'
import {
  computeBoardMetrics,
  computeStageMetrics,
  isClosedTask,
  isDueToday,
  isOverdue,
} from '@/features/work-orders/task-board/task-board-metrics'

const TODAY = '2026-09-22'

const OPEN_STATUS = { id: 1, name: 'Aperto', color: 'blue', icon: null, system_key: null, group: 'open' as const, completion_percentage: 0 }
const CLOSED_POSITIVE_STATUS = { id: 2, name: 'Chiuso', color: 'green', icon: null, system_key: 'closed_positive' as const, group: 'closed_positive' as const, completion_percentage: 100 }
const CLOSED_NEGATIVE_STATUS = { id: 3, name: 'Fallito', color: 'red', icon: null, system_key: 'closed_negative' as const, group: 'closed_negative' as const, completion_percentage: 100 }

describe('isClosedTask', () => {
  it('is true for closed_positive and closed_negative, false otherwise', () => {
    expect(isClosedTask(boardTask({ task_status: CLOSED_POSITIVE_STATUS }))).toBe(true)
    expect(isClosedTask(boardTask({ task_status: CLOSED_NEGATIVE_STATUS }))).toBe(true)
    expect(isClosedTask(boardTask({ task_status: OPEN_STATUS }))).toBe(false)
  })
})

describe('isOverdue', () => {
  it('is true only when the task is not closed and its due reference is strictly before today', () => {
    expect(isOverdue(boardTask({ task_status: OPEN_STATUS, end_date: '2026-09-21' }), TODAY)).toBe(true)
    expect(isOverdue(boardTask({ task_status: CLOSED_POSITIVE_STATUS, end_date: '2026-09-21' }), TODAY)).toBe(false)
    expect(isOverdue(boardTask({ task_status: OPEN_STATUS, end_date: TODAY }), TODAY)).toBe(false)
    expect(isOverdue(boardTask({ task_status: OPEN_STATUS, end_date: null, start_date: null }), TODAY)).toBe(false)
  })

  it('falls back to start_date when end_date is unset', () => {
    expect(isOverdue(boardTask({ task_status: OPEN_STATUS, end_date: null, start_date: '2026-09-01' }), TODAY)).toBe(true)
  })
})

describe('isDueToday', () => {
  it('matches end_date, falling back to start_date', () => {
    expect(isDueToday(boardTask({ end_date: TODAY }), TODAY)).toBe(true)
    expect(isDueToday(boardTask({ end_date: null, start_date: TODAY }), TODAY)).toBe(true)
    expect(isDueToday(boardTask({ end_date: '2026-09-23' }), TODAY)).toBe(false)
  })
})

describe('computeBoardMetrics', () => {
  it('aggregates total/completion/overdue/dueToday/estimated/actual over the given roots', () => {
    const roots = [
      boardTask({ id: 1, task_status: CLOSED_POSITIVE_STATUS, end_date: '2026-09-10', estimated_minutes: 30, actual_minutes: 20 }),
      boardTask({ id: 2, task_status: OPEN_STATUS, end_date: '2026-09-21', estimated_minutes: 60, actual_minutes: 0 }),
      boardTask({ id: 3, task_status: OPEN_STATUS, end_date: TODAY, estimated_minutes: null, actual_minutes: 15 }),
    ]

    const metrics = computeBoardMetrics(roots, TODAY)

    expect(metrics.total).toBe(3)
    expect(metrics.completionPercentage).toBe(33)
    expect(metrics.overdueCount).toBe(1)
    expect(metrics.dueTodayCount).toBe(1)
    expect(metrics.estimatedMinutes).toBe(90)
    expect(metrics.actualMinutes).toBe(35)
  })

  it('returns 0% completion for an empty set, without dividing by zero', () => {
    expect(computeBoardMetrics([], TODAY).completionPercentage).toBe(0)
  })
})

describe('computeStageMetrics', () => {
  it('aggregates count/completionPercentage/estimated/actual over a single fase group', () => {
    const roots = [
      boardTask({ id: 1, task_status: CLOSED_POSITIVE_STATUS, estimated_minutes: 40, actual_minutes: 40 }),
      boardTask({ id: 2, task_status: OPEN_STATUS, estimated_minutes: 20, actual_minutes: 5 }),
    ]

    const metrics = computeStageMetrics(roots)

    expect(metrics.count).toBe(2)
    expect(metrics.completionPercentage).toBe(50)
    expect(metrics.estimatedMinutes).toBe(60)
    expect(metrics.actualMinutes).toBe(45)
  })

  it('averages the completion of the tasks inside, so a half-done task counts for half', () => {
    const halfDone = { ...OPEN_STATUS, id: 4, name: 'In corso', completion_percentage: 50 }
    const roots = [
      boardTask({ id: 1, task_status: CLOSED_POSITIVE_STATUS }),
      boardTask({ id: 2, task_status: halfDone }),
      boardTask({ id: 3, task_status: OPEN_STATUS }),
      boardTask({ id: 4, task_status: halfDone }),
    ]

    expect(computeStageMetrics(roots).completionPercentage).toBe(50)
    expect(computeStageMetrics([]).completionPercentage).toBe(0)
  })
})
