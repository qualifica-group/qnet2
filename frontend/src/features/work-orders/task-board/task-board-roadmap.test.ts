import { describe, expect, it } from 'vitest'
import {
  ROADMAP_MIN_WEEKS,
  buildRoadmapRange,
  daysBetween,
  mergeSpans,
  roadmapBar,
  roadmapSpan,
  roadmapWeeks,
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

describe('buildRoadmapRange', () => {
  it('starts on a Monday, covers today and is at least the minimum width when nothing is dated', () => {
    const range = buildRoadmapRange([{ start_date: null, end_date: null }], TODAY)

    expect(range.start).toBe('2026-11-16') // Monday of the week before today's
    expect(range.days).toBe(ROADMAP_MIN_WEEKS * 7)
    expect(daysBetween(range.start, TODAY)).toBeGreaterThanOrEqual(0)
  })

  it('pads one week around the earliest and latest dates', () => {
    const range = buildRoadmapRange(
      [
        { start_date: '2026-09-02', end_date: '2026-09-10' },
        { start_date: null, end_date: '2027-03-03' },
      ],
      TODAY,
    )

    expect(range.start).toBe('2026-08-24') // Monday 2026-08-31 minus one week
    const lastWeek = roadmapWeeks(range).at(-1)!
    expect(lastWeek.monday).toBe('2027-03-08') // Monday 2027-03-01 plus one week
    expect(range.days % 7).toBe(0)
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
