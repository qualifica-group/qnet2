import { describe, expect, it } from 'vitest'
import { periodElapsedPercent } from '@/features/contracts/contract-renewal-periods'

describe('periodElapsedPercent', () => {
  it('measures the elapsed share from the server day count', () => {
    expect(periodElapsedPercent('2026-01-01', '2026-01-11', 4)).toBe(60)
  })

  it('clamps before the start and after the expiry', () => {
    expect(periodElapsedPercent('2026-01-01', '2026-01-11', 20)).toBe(0)
    expect(periodElapsedPercent('2026-01-01', '2026-01-11', -3)).toBe(100)
  })

  it('accepts datetime strings, reading only the date part', () => {
    expect(periodElapsedPercent('2026-01-01T10:00:00Z', '2026-01-11', 5)).toBe(50)
  })

  it('is null when a date is missing or the period has no length', () => {
    expect(periodElapsedPercent(null, '2026-01-11', 4)).toBeNull()
    expect(periodElapsedPercent('2026-01-01', null, 4)).toBeNull()
    expect(periodElapsedPercent('2026-01-01', '2026-01-11', null)).toBeNull()
    expect(periodElapsedPercent('2026-01-11', '2026-01-11', 0)).toBeNull()
  })
})
