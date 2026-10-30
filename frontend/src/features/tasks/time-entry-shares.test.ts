import { describe, expect, it } from 'vitest'
import { shareSlots, splitMinutesEvenly, validateShares } from '@/features/tasks/time-entry-shares'

describe('splitMinutesEvenly', () => {
  it('gives the remainder to the first tasks in order (AC-009)', () => {
    const { shares, error } = splitMinutesEvenly(180, [1, 2, 3, 4, 5, 6, 7])

    expect(error).toBeNull()
    expect(shares.map((share) => share.minutes)).toEqual([26, 26, 26, 26, 26, 25, 25])
    expect(shares.map((share) => share.task_id)).toEqual([1, 2, 3, 4, 5, 6, 7])
  })

  it('reports too few minutes when the total is below the task count (AC-009)', () => {
    const { shares, error } = splitMinutesEvenly(5, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10])

    expect(error).toBe('too_few_minutes')
    expect(shares).toHaveLength(10)
  })
})

describe('shareSlots', () => {
  it('builds consecutive slots from the start time (AC-009)', () => {
    const shares = [
      { task_id: 1, minutes: 90 },
      { task_id: 2, minutes: 45 },
      { task_id: 3, minutes: 45 },
    ]

    expect(shareSlots('09:00', shares)).toEqual([
      { taskId: 1, startTime: '09:00', endTime: '10:30' },
      { taskId: 2, startTime: '10:30', endTime: '11:15' },
      { taskId: 3, startTime: '11:15', endTime: '12:00' },
    ])
  })

  it('returns null without a start time', () => {
    expect(shareSlots(null, [{ task_id: 1, minutes: 30 }])).toBeNull()
  })
})

describe('validateShares', () => {
  it('flags a quota below 1 minute before a sum mismatch', () => {
    expect(validateShares(10, [{ task_id: 1, minutes: 10 }, { task_id: 2, minutes: 0 }])).toBe('too_few_minutes')
  })

  it('flags a sum different from the total', () => {
    expect(validateShares(10, [{ task_id: 1, minutes: 4 }, { task_id: 2, minutes: 5 }])).toBe('mismatch')
  })

  it('accepts quotas adding up to the total', () => {
    expect(validateShares(10, [{ task_id: 1, minutes: 4 }, { task_id: 2, minutes: 6 }])).toBeNull()
  })
})
