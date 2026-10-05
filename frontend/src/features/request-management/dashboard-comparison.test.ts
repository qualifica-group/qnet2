import { describe, expect, it } from 'vitest'
import { compareIndicator, valuesByKey } from '@/features/request-management/dashboard-comparison'

/** Spec 0192 D-4: change against the previous period, inverted for unhandled work. */

describe('compareIndicator', () => {
  it('is a positive rise for a regular indicator', () => {
    expect(compareIndicator('telefonate', 12, 8)).toEqual({ direction: 'up', percent: 50, sentiment: 'positive', previous: 8 })
  })

  it('is a negative drop for a regular indicator, rounded', () => {
    expect(compareIndicator('associati', 2, 3)).toEqual({ direction: 'down', percent: -33, sentiment: 'negative', previous: 3 })
  })

  it('reads a rise of unhandled work as bad news and a drop as good news (AC-004)', () => {
    expect(compareIndicator('unhandled_callbacks', 6, 3)?.sentiment).toBe('negative')
    expect(compareIndicator('unhandled_new_contacts', 1, 4)?.sentiment).toBe('positive')
  })

  it('is neutral when unchanged', () => {
    expect(compareIndicator('telefonate', 5, 5)).toEqual({ direction: 'flat', percent: 0, sentiment: 'neutral', previous: 5 })
  })

  it('marks a value out of a zero previous period as new, with no percent', () => {
    expect(compareIndicator('telefonate', 4, 0)).toEqual({ direction: 'up', percent: null, sentiment: 'positive', previous: 0 })
  })

  it('shows nothing for zero in both periods or no previous value', () => {
    expect(compareIndicator('telefonate', 0, 0)).toBeNull()
    expect(compareIndicator('telefonate', 4, undefined)).toBeNull()
  })
})

describe('valuesByKey', () => {
  it('indexes the previous values by indicator key', () => {
    const values = valuesByKey([{ key: 'telefonate', label: 'Telefonate', value: 3 }])
    expect(values?.get('telefonate')).toBe(3)
    expect(valuesByKey(undefined)).toBeUndefined()
  })
})
