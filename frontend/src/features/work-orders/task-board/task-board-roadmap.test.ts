import { describe, expect, it } from 'vitest'
import {
  ROADMAP_SCALE_CONFIGS,
  buildRoadmapRange,
  daysBetween,
  mergeSpans,
  roadmapBar,
  roadmapSlices,
  roadmapSpan,
} from '@/features/work-orders/task-board/task-board-roadmap'

const TODAY = '2026-11-25' // a Wednesday

describe('roadmapSpan', () => {
  it('is a bar when both dates are set', () => {
    expect(roadmapSpan({ start_date: '2026-11-02', end_date: '2026-11-06' })).toEqual({
      start: '2026-11-02',
      end: '2026-11-06',
      isMilestone: false,
    })
  })

  it('is a one-day milestone on the only date set', () => {
    expect(roadmapSpan({ start_date: null, end_date: '2026-11-06' })).toEqual({ start: '2026-11-06', end: '2026-11-06', isMilestone: true })
    expect(roadmapSpan({ start_date: '2026-11-02', end_date: null })).toEqual({ start: '2026-11-02', end: '2026-11-02', isMilestone: true })
  })

  it('is null without any date', () => {
    expect(roadmapSpan({ start_date: null, end_date: null })).toBeNull()
  })

  it('reads inverted dates in calendar order', () => {
    expect(roadmapSpan({ start_date: '2026-11-06', end_date: '2026-11-02' })?.start).toBe('2026-11-02')
  })
})

const DATED_TASKS = [
  { start_date: '2026-09-02', end_date: '2026-09-10' },
  { start_date: null, end_date: '2027-03-03' },
]

describe('buildRoadmapRange', () => {
  it('week scale: starts on a Monday, covers today and is at least the minimum width when nothing is dated', () => {
    const range = buildRoadmapRange([{ start_date: null, end_date: null }], TODAY, 'week')

    expect(range.start).toBe('2026-11-16') // Monday of the week before today's
    expect(range.days).toBe(ROADMAP_SCALE_CONFIGS.week.minUnits * 7)
    expect(daysBetween(range.start, TODAY)).toBeGreaterThanOrEqual(0)
  })

  it('week scale: pads one week around the earliest and latest dates', () => {
    const range = buildRoadmapRange(DATED_TASKS, TODAY, 'week')

    expect(range.start).toBe('2026-08-24') // Monday 2026-08-31 minus one week
    const lastWeek = roadmapSlices(range, 'week').at(-1)!
    expect(lastWeek.start).toBe('2027-03-08') // Monday 2027-03-01 plus one week
    expect(range.days % 7).toBe(0)
  })

  it('month scale: snaps to whole months with one month of padding', () => {
    const range = buildRoadmapRange(DATED_TASKS, TODAY, 'month')

    expect(range.start).toBe('2026-08-01')
    expect(roadmapSlices(range, 'month').map((month) => month.start)).toEqual([
      '2026-08-01',
      '2026-09-01',
      '2026-10-01',
      '2026-11-01',
      '2026-12-01',
      '2027-01-01',
      '2027-02-01',
      '2027-03-01',
      '2027-04-01',
    ])
  })

  it('month scale: is at least six months wide', () => {
    const range = buildRoadmapRange([], TODAY, 'month')

    expect(range.start).toBe('2026-10-01')
    expect(roadmapSlices(range, 'month')).toHaveLength(ROADMAP_SCALE_CONFIGS.month.minUnits)
  })

  it('year scale: covers whole calendar years, twelve months each', () => {
    const range = buildRoadmapRange(DATED_TASKS, TODAY, 'year')

    expect(range).toEqual({ start: '2026-01-01', days: 365 + 365 })
    expect(roadmapSlices(range, 'year').map((year) => year.start)).toEqual(['2026-01-01', '2027-01-01'])
    expect(roadmapSlices(range, 'month')).toHaveLength(24)
  })
})

describe('roadmapSlices', () => {
  it('clips a unit the window cuts into, labelled by the unit start', () => {
    const months = roadmapSlices({ start: '2026-11-16', days: 28 }, 'month')

    expect(months).toEqual([
      { start: '2026-11-01', offset: 0, days: 15 },
      { start: '2026-12-01', offset: 15, days: 13 },
    ])
  })
})

describe('roadmapBar / mergeSpans', () => {
  it('places a bar in days from the window start, both ends included', () => {
    const range = { start: '2026-11-02', days: 56 }
    expect(roadmapBar({ start: '2026-11-04', end: '2026-11-06', isMilestone: false }, range)).toEqual({ offset: 2, length: 3 })
  })

  it('merges a fase stretch over its dated tasks only', () => {
    expect(
      mergeSpans([
        { start: '2026-11-04', end: '2026-11-06', isMilestone: false },
        null,
        { start: '2026-11-01', end: '2026-11-03', isMilestone: false },
      ]),
    ).toEqual({ start: '2026-11-01', end: '2026-11-06', isMilestone: false })
    expect(mergeSpans([null])).toBeNull()
  })
})
