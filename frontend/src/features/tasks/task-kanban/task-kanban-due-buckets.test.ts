import { describe, expect, it } from 'vitest'
import {
  classifyTaskDueBucket,
  dueBucketDropDate,
  isDueBucketDraggableFrom,
  isDueBucketDroppable,
} from '@/features/tasks/task-kanban/task-kanban-due-buckets'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

const TODAY = '2026-09-24' // Thursday

function row(overrides: Partial<TaskKanbanRow> = {}): TaskKanbanRow {
  return {
    id: 1,
    actions: [],
    title: 'Task',
    task_status: { id: 1, name: 'Aperto', color: 'blue', icon: null, group: 'open' },
    task_priority: null,
    start_date: null,
    end_date: null,
    completion_percentage: 0,
    estimated_minutes: null,
    actual_minutes: 0,
    is_blocked: false,
    assignees: [],
    has_subtasks: false,
    ...overrides,
  }
}

describe('classifyTaskDueBucket', () => {
  it('puts a closed task in "completed" regardless of its date', () => {
    const closed = row({
      task_status: { id: 2, name: 'Chiuso', color: 'green', icon: null, group: 'closed_positive' },
      end_date: '2020-01-01',
    })
    expect(classifyTaskDueBucket(closed, TODAY)).toBe('completed')
  })

  it('puts a task with no due reference in "this_month"', () => {
    expect(classifyTaskDueBucket(row(), TODAY)).toBe('this_month')
  })

  it('classifies overdue/today/tomorrow/this_week/this_month/later on an open task', () => {
    expect(classifyTaskDueBucket(row({ end_date: '2026-09-20' }), TODAY)).toBe('overdue')
    expect(classifyTaskDueBucket(row({ end_date: TODAY }), TODAY)).toBe('today')
    expect(classifyTaskDueBucket(row({ end_date: '2026-09-25' }), TODAY)).toBe('tomorrow')
    // Sunday of this ISO week is 2026-09-27.
    expect(classifyTaskDueBucket(row({ end_date: '2026-09-27' }), TODAY)).toBe('this_week')
    // End of this month (September) is 2026-09-30.
    expect(classifyTaskDueBucket(row({ end_date: '2026-09-30' }), TODAY)).toBe('this_month')
    // Requirement changed (user directive): a reference past the end of this
    // month is "later" ("Più avanti"), not the old "this_month" catch-all.
    expect(classifyTaskDueBucket(row({ end_date: '2026-10-15' }), TODAY)).toBe('later')
  })

  it('falls back to start_date when end_date is null', () => {
    expect(classifyTaskDueBucket(row({ start_date: TODAY }), TODAY)).toBe('today')
  })
})

describe('drop rules', () => {
  it('refuses a drop onto overdue/completed', () => {
    expect(isDueBucketDroppable('overdue')).toBe(false)
    expect(isDueBucketDroppable('completed')).toBe(false)
    expect(isDueBucketDroppable('today')).toBe(true)
    expect(isDueBucketDroppable('this_month')).toBe(true)
    expect(isDueBucketDroppable('later')).toBe(true)
  })

  it('locks dragging out of completed only', () => {
    expect(isDueBucketDraggableFrom('completed')).toBe(false)
    expect(isDueBucketDraggableFrom('overdue')).toBe(true)
    expect(isDueBucketDraggableFrom('today')).toBe(true)
  })
})

describe('dueBucketDropDate', () => {
  it('computes today/tomorrow/end of week/end of month/1st of next month', () => {
    expect(dueBucketDropDate('today', TODAY)).toBe('2026-09-24')
    expect(dueBucketDropDate('tomorrow', TODAY)).toBe('2026-09-25')
    expect(dueBucketDropDate('this_week', TODAY)).toBe('2026-09-27')
    expect(dueBucketDropDate('this_month', TODAY)).toBe('2026-09-30')
    expect(dueBucketDropDate('later', TODAY)).toBe('2026-10-01')
  })

  it('rolls over the year when dropping "later" in December', () => {
    expect(dueBucketDropDate('later', '2026-12-15')).toBe('2027-01-01')
  })

  it('returns null for a non-droppable bucket', () => {
    expect(dueBucketDropDate('overdue', TODAY)).toBeNull()
    expect(dueBucketDropDate('completed', TODAY)).toBeNull()
  })
})
