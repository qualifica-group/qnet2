import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { currentWeekReportRange } from '@/features/request-management/request-report-schema'

/**
 * Rev-2 AC-056/AC-057: the current week's Monday/Friday, computed from LOCAL
 * date components. `process.env.TZ` is set per test (Node honors it for
 * subsequently-constructed `Date` objects) so the east-of-UTC case is
 * genuine, not dependent on the machine running the suite.
 */

const ORIGINAL_TZ = process.env.TZ

beforeEach(() => {
  process.env.TZ = 'UTC'
})

afterEach(() => {
  process.env.TZ = ORIGINAL_TZ
})

describe('currentWeekReportRange', () => {
  it('resolves the same week\'s Monday/Friday from a midweek Wednesday (AC-056)', () => {
    // 2026-09-09 is a Wednesday.
    expect(currentWeekReportRange(new Date(2026, 8, 9, 12, 0))).toEqual({
      date_from: '2026-09-07',
      date_to: '2026-09-11',
    })
  })

  it('resolves the PRECEDING Monday from a Sunday, not the next one (AC-056)', () => {
    // 2026-09-06 is a Sunday; `getDay() - 1` would wrongly jump six days
    // forward instead of one day back — the offset must be `(getDay()+6)%7`.
    expect(currentWeekReportRange(new Date(2026, 8, 6, 12, 0))).toEqual({
      date_from: '2026-08-31',
      date_to: '2026-09-04',
    })
  })

  it('never crosses into the next week from a Saturday, even proposing a Friday in the past', () => {
    // 2026-09-05 is a Saturday, still inside the week of Monday 2026-08-31.
    expect(currentWeekReportRange(new Date(2026, 8, 5, 9, 0))).toEqual({
      date_from: '2026-08-31',
      date_to: '2026-09-04',
    })
  })

  it('uses LOCAL components, not toISOString(), just after midnight east of UTC (AC-057)', () => {
    process.env.TZ = 'Asia/Tokyo'
    // 2026-09-07 00:30 Tokyo time (UTC+9) is genuinely Monday locally; its
    // UTC equivalent is 2026-09-06 15:30 — `toISOString().slice(0,10)` would
    // wrongly read Sunday and propose the wrong week entirely.
    expect(currentWeekReportRange(new Date(2026, 8, 7, 0, 30))).toEqual({
      date_from: '2026-09-07',
      date_to: '2026-09-11',
    })
  })
})
