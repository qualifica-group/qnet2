import { describe, expect, it } from 'vitest'
import {
  buildForecastPayload,
  NO_FORECAST,
  toForecastValues,
} from '@/features/request-management/request-forecast'

describe('request forecast (spec 0313)', () => {
  it('hydrates the decimal string and shows an unset probability as 0', () => {
    expect(toForecastValues({ expected_close_date: '2026-12-01', estimated_value: '1500.00', success_probability: null }))
      .toEqual({ expected_close_date: '2026-12-01', estimated_value: 1500, success_probability: 0 })
  })

  it('omits every key that round-trips unchanged', () => {
    const persisted = { expected_close_date: '2026-12-01', estimated_value: '1500.00', success_probability: 40 }

    expect(buildForecastPayload(toForecastValues(persisted), persisted)).toEqual({})
  })

  it('sends a changed key, and null to clear a persisted one', () => {
    const persisted = { expected_close_date: '2026-12-01', estimated_value: '1500.00', success_probability: 40 }

    expect(
      buildForecastPayload({ expected_close_date: null, estimated_value: 1500, success_probability: 70 }, persisted),
    ).toEqual({ expected_close_date: null, success_probability: 70 })
  })

  it('on create sends only the values actually set', () => {
    expect(buildForecastPayload(toForecastValues(NO_FORECAST), NO_FORECAST)).toEqual({})
    expect(
      buildForecastPayload({ expected_close_date: '2027-01-15', estimated_value: 800, success_probability: 0 }, NO_FORECAST),
    ).toEqual({ expected_close_date: '2027-01-15', estimated_value: 800 })
  })
})
