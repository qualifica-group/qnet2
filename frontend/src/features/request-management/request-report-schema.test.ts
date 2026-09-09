import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildRequestReportSchema,
  currentWeekReportRange,
  isRequestReportQueryReady,
  toRequestReportFilterPayload,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'

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

/**
 * Spec 0112 AC-017/AC-020: the site axis on the wire and in the validation
 * rules. Pure functions, so they are exercised directly — the group's own UI
 * lives in `request-report-site-filter.test.tsx`.
 */

const OPERATOR_KEYS = ['11', 'unassigned']
const SITE_KEYS = ['3', '7']

function values(overrides: Partial<RequestReportFormValues> = {}): RequestReportFormValues {
  return {
    date_from: '2026-09-07',
    date_to: '2026-09-11',
    category_keys: ['gol'],
    row_mode: 'all',
    operator_keys: OPERATOR_KEYS,
    site_keys: SITE_KEYS,
    ...overrides,
  }
}

describe('toRequestReportFilterPayload — site_keys (AC-017)', () => {
  it('sends the picked sites when only some of them are selected', () => {
    const payload = toRequestReportFilterPayload(values({ site_keys: ['3'] }), OPERATOR_KEYS, SITE_KEYS)

    expect(payload.site_keys).toEqual(['3'])
  })

  it('omits site_keys under "total only", where there are no operator rows to narrow (D-7)', () => {
    const payload = toRequestReportFilterPayload(
      values({ site_keys: ['3'], row_mode: 'total_only' }),
      OPERATOR_KEYS,
      SITE_KEYS,
    )

    expect(payload).not.toHaveProperty('site_keys')
  })

  it('omits site_keys when the picker offers nothing', () => {
    const payload = toRequestReportFilterPayload(values({ site_keys: [] }), OPERATOR_KEYS, [])

    expect(payload).not.toHaveProperty('site_keys')
  })

  it('omits site_keys on a full selection, so a site added later is still included (D-4)', () => {
    const payload = toRequestReportFilterPayload(values(), OPERATOR_KEYS, SITE_KEYS)

    expect(payload).not.toHaveProperty('site_keys')
  })

  it('narrows the two axes independently (D-10)', () => {
    const payload = toRequestReportFilterPayload(values({ site_keys: ['7'] }), OPERATOR_KEYS, SITE_KEYS)

    // Every operator selected -> that field stays off the wire, while the
    // partial site selection travels.
    expect(payload).not.toHaveProperty('operator_keys')
    expect(payload.site_keys).toEqual(['7'])
  })
})

describe('site selection rules (AC-020)', () => {
  it('requires at least one site once the picker has options', () => {
    const result = buildRequestReportSchema(i18n.t, [], SITE_KEYS).safeParse(values({ site_keys: [] }))

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => issue.path.join('.'))).toContain('site_keys')
  })

  it('stays inert with an empty picker: an error the user could never clear', () => {
    expect(buildRequestReportSchema(i18n.t, [], []).safeParse(values({ site_keys: [] })).success).toBe(true)
  })

  it('stays inert under "total only"', () => {
    const result = buildRequestReportSchema(i18n.t, [], SITE_KEYS).safeParse(
      values({ site_keys: [], row_mode: 'total_only' }),
    )

    expect(result.success).toBe(true)
  })

  it('gates the dashboard query on the same rule, synchronously', () => {
    expect(isRequestReportQueryReady(values({ site_keys: [] }), [], SITE_KEYS)).toBe(false)
    expect(isRequestReportQueryReady(values({ site_keys: [] }), [], [])).toBe(true)
    expect(isRequestReportQueryReady(values({ site_keys: [], row_mode: 'total_only' }), [], SITE_KEYS)).toBe(
      true,
    )
    expect(isRequestReportQueryReady(values({ site_keys: ['3'] }), [], SITE_KEYS)).toBe(true)
  })
})
