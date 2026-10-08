import { describe, expect, it } from 'vitest'
import { taskEndDateState } from '@/features/tasks/task-due-state'

const TODAY = '2026-10-06'

describe('taskEndDateState (user directive 2026-10-06, "come in commesse")', () => {
  it('marks an open task whose end date has passed as overdue', () => {
    expect(taskEndDateState('2026-10-05', 'open', TODAY)).toBe('overdue')
    expect(taskEndDateState('2026-09-01', null, TODAY)).toBe('overdue')
  })

  it('marks an open task ending today as today', () => {
    expect(taskEndDateState(TODAY, 'pending', TODAY)).toBe('today')
  })

  it('leaves a future end date, a missing one and a closed task alone', () => {
    expect(taskEndDateState('2026-10-07', 'open', TODAY)).toBeNull()
    expect(taskEndDateState(null, 'open', TODAY)).toBeNull()
    expect(taskEndDateState('2026-10-01', 'closed_positive', TODAY)).toBeNull()
    expect(taskEndDateState(TODAY, 'closed_negative', TODAY)).toBeNull()
  })
})
