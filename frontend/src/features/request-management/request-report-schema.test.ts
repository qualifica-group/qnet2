import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  buildRequestReportSchema,
  isRequestReportQueryReady,
  todayReportRange,
  toRequestReportFilterPayload,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'

/**
 * Spec 0169 D-1 (replacing rev-2 AC-056): today as both bounds, still built
 * from LOCAL date components (AC-057). `process.env.TZ` is set per test (Node
 * honors it for subsequently-constructed `Date` objects) so the east-of-UTC
 * case is genuine, not dependent on the machine running the suite.
 */

const ORIGINAL_TZ = process.env.TZ

beforeEach(() => {
  process.env.TZ = 'UTC'
})

afterEach(() => {
  process.env.TZ = ORIGINAL_TZ
})

describe('todayReportRange', () => {
  it('proposes today as both From and To (spec 0169 D-1)', () => {
    expect(todayReportRange(new Date(2026, 8, 9, 12, 0))).toEqual({
      date_from: '2026-09-09',
      date_to: '2026-09-09',
    })
  })

  it('uses LOCAL components, not toISOString(), just after midnight east of UTC (AC-057)', () => {
    process.env.TZ = 'Asia/Tokyo'
    // 2026-09-07 00:30 Tokyo time (UTC+9) is locally the 7th; its UTC
    // equivalent is 2026-09-06 15:30 — `toISOString()` would read the 6th.
    expect(todayReportRange(new Date(2026, 8, 7, 0, 30))).toEqual({
      date_from: '2026-09-07',
      date_to: '2026-09-07',
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

/** Spec 0169 D-2/AC-002..005: either bound may be left empty, an open side. */
describe('open date bounds (spec 0169)', () => {
  const schema = () => buildRequestReportSchema(i18n.t, OPERATOR_KEYS, SITE_KEYS)

  it.each([
    ['only To', { date_from: '', date_to: '2026-09-25' }],
    ['only From', { date_from: '2026-09-01', date_to: '' }],
    ['neither', { date_from: '', date_to: '' }],
  ])('accepts %s in the schema and as a ready query', (_case, dates) => {
    expect(schema().safeParse(values(dates)).success).toBe(true)
    expect(isRequestReportQueryReady(values(dates), OPERATOR_KEYS, SITE_KEYS)).toBe(true)
  })

  it('still rejects To before From when both are set (AC-005)', () => {
    const dates = { date_from: '2026-09-30', date_to: '2026-09-01' }

    expect(schema().safeParse(values(dates)).success).toBe(false)
    expect(isRequestReportQueryReady(values(dates), OPERATOR_KEYS, SITE_KEYS)).toBe(false)
  })

  it('drops an empty bound from the wire and keeps the filled one (AC-006)', () => {
    const onlyTo = toRequestReportFilterPayload(values({ date_from: '' }), OPERATOR_KEYS, SITE_KEYS)
    const neither = toRequestReportFilterPayload(values({ date_from: '', date_to: '' }), OPERATOR_KEYS, SITE_KEYS)

    expect(onlyTo).not.toHaveProperty('date_from')
    expect(onlyTo.date_to).toBe('2026-09-11')
    expect(neither).not.toHaveProperty('date_from')
    expect(neither).not.toHaveProperty('date_to')
  })
})
