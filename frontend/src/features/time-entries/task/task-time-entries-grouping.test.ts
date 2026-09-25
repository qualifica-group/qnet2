import { describe, expect, it } from 'vitest'
import {
  groupTaskTimeEntriesByDay,
  limitTaskTimeEntryGroups,
} from '@/features/time-entries/task/task-time-entries-grouping'
import { getTodayDateKey } from '@/features/time-entries/time-entry-period'
import type { TimeEntry } from '@/features/time-entries/types'

const T = ((key: string) => key) as unknown as Parameters<typeof groupTaskTimeEntriesByDay>[1]

function buildEntry(overrides: Partial<TimeEntry> & { id: number; date: string; minutes: number }): TimeEntry {
  return {
    user: { id: 1, name: 'Utente' },
    title: 'Task',
    task_type: { id: 1, name: 'Attività', color: 'sky', icon: null },
    start_time: null,
    end_time: null,
    notes: null,
    registry: null,
    opportunity: null,
    work_order: null,
    task: null,
    work_order_stage: null,
    created_at: '2026-09-14T10:00:00Z',
    updated_at: '2026-09-14T10:00:00Z',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

describe('groupTaskTimeEntriesByDay', () => {
  it("labels today's and yesterday's groups and sums each day's minutes", () => {
    const today = getTodayDateKey()
    const entries = [
      buildEntry({ id: 1, date: today, minutes: 30 }),
      buildEntry({ id: 2, date: today, minutes: 15 }),
      buildEntry({ id: 3, date: '2026-01-01', minutes: 60 }),
    ]

    const groups = groupTaskTimeEntriesByDay(entries, T)

    expect(groups).toHaveLength(2)
    expect(groups[0]).toMatchObject({ label: 'timeEntries.task.today', totalMinutes: 45 })
    expect(groups[0].entries).toHaveLength(2)
    expect(groups[1].totalMinutes).toBe(60)
  })
})

describe('limitTaskTimeEntryGroups', () => {
  it('cuts a group short instead of dropping it once the limit is reached', () => {
    const groups = groupTaskTimeEntriesByDay(
      [
        buildEntry({ id: 1, date: '2026-01-03', minutes: 10 }),
        buildEntry({ id: 2, date: '2026-01-02', minutes: 20 }),
        buildEntry({ id: 3, date: '2026-01-02', minutes: 30 }),
        buildEntry({ id: 4, date: '2026-01-01', minutes: 40 }),
      ],
      T,
    )

    const limited = limitTaskTimeEntryGroups(groups, 2)

    expect(limited).toHaveLength(2)
    expect(limited[0].entries).toHaveLength(1)
    expect(limited[1].entries).toHaveLength(1)
    expect(limited[1].totalMinutes).toBe(20)
  })
})
