import { describe, expect, it } from 'vitest'
import { Clock, Phone } from 'lucide-react'
import {
  buildDayComposition,
  buildHourMarks,
  resolveTaskTypeIcon,
} from '@/features/time-entries/days/time-entry-day-view-model'
import type { DaySummary, TimeEntry } from '@/features/time-entries/types'

function taskType(overrides: Partial<TimeEntry['task_type']> = {}): TimeEntry['task_type'] {
  return { id: 1, name: 'Attivita', color: 'blue', icon: 'phone', ...overrides }
}

function entry(overrides: Partial<TimeEntry> = {}): TimeEntry {
  return {
    id: 1,
    user: { id: 1, name: 'Mario' },
    date: '2026-09-14',
    title: 'Entry',
    task_type: taskType(),
    start_time: null,
    end_time: null,
    minutes: 60,
    notes: null,
    registry: null,
    opportunity: null,
    work_order: null,
    task: null,
    created_at: '',
    updated_at: '',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

function daySummary(overrides: Partial<DaySummary> = {}): DaySummary {
  return {
    date: '2026-09-14',
    weekday: 1,
    is_holiday: false,
    is_non_working_day: false,
    is_active: true,
    target_minutes: 480,
    total_minutes: 450,
    utilization_percentage: 93.75,
    status: 'under_target',
    day_note: null,
    entries: [],
    ...overrides,
  }
}

describe('buildHourMarks', () => {
  it('places one tick per hour, evenly spaced', () => {
    expect(buildHourMarks(120)).toEqual([
      { leftPercent: 0, label: '0h' },
      { leftPercent: 50, label: '1h' },
      { leftPercent: 100, label: '2h' },
    ])
  })
})

describe('buildDayComposition (AC-035)', () => {
  it('computes the scale, target offset and total/target percentage on an under-target day', () => {
    const day = daySummary({
      target_minutes: 480,
      total_minutes: 450,
      entries: [
        entry({ id: 1, task_type: taskType({ id: 1, name: 'Attivita' }), minutes: 300 }),
        entry({ id: 2, task_type: taskType({ id: 2, name: 'Riunione' }), minutes: 150 }),
      ],
    })

    const composition = buildDayComposition(day)

    expect(composition.scaleCeilingMinutes).toBe(480)
    expect(composition.targetOffsetPercent).toBe(100)
    expect(composition.totalOverTargetPercent).toBe(94) // round(450/480*100)
  })

  it('sorts segments/distribution by minutes desc and derives correct percentages', () => {
    const day = daySummary({
      target_minutes: 480,
      total_minutes: 450,
      entries: [
        entry({ id: 1, task_type: taskType({ id: 1, name: 'Attivita' }), minutes: 300 }),
        entry({ id: 2, task_type: taskType({ id: 2, name: 'Riunione' }), minutes: 150 }),
      ],
    })

    const composition = buildDayComposition(day)

    expect(composition.segments.map((segment) => segment.taskTypeId)).toEqual([1, 2])
    expect(composition.segments[0].widthPercent).toBeCloseTo(62.5)
    expect(composition.segments[1].widthPercent).toBeCloseTo(31.25)
    expect(composition.distribution[0]).toMatchObject({ taskTypeId: 1, minutes: 300, percentage: 67 })
    expect(composition.distribution[1]).toMatchObject({ taskTypeId: 2, minutes: 150, percentage: 33 })
  })

  it('clamps a tiny segment to the minimum visible width', () => {
    const day = daySummary({
      target_minutes: 480,
      total_minutes: 480,
      entries: [entry({ id: 1, minutes: 5 })],
    })

    const composition = buildDayComposition(day)

    expect(composition.segments[0].widthPercent).toBe(2)
  })

  it('reports no target line/percentage on a no_target day (D-7)', () => {
    const day = daySummary({ target_minutes: 0, total_minutes: 30, status: 'no_target' })

    const composition = buildDayComposition(day)

    expect(composition.targetOffsetPercent).toBeNull()
    expect(composition.totalOverTargetPercent).toBeNull()
  })
})

describe('resolveTaskTypeIcon', () => {
  it('resolves a curated icon name to its lucide component', () => {
    expect(resolveTaskTypeIcon('phone')).toBe(Phone)
  })

  it('falls back to the neutral icon when the name is null or not curated', () => {
    expect(resolveTaskTypeIcon(null)).toBe(Clock)
    expect(resolveTaskTypeIcon('not-a-real-icon')).toBe(Clock)
  })
})
