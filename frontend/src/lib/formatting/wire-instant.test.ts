import { describe, expect, it } from 'vitest'
import { joinInstant, splitInstant } from '@/lib/formatting/wire-instant'

/**
 * User directive 2026-07-31: a planned instant must not force an hour. The
 * wire format stays `YYYY-MM-DDTHH:mm` — a date picked alone commits midnight,
 * and midnight reads back as "no time set".
 */

describe('splitInstant', () => {
  it('splits a wire instant into its date and time parts', () => {
    expect(splitInstant('2026-08-03T15:30')).toEqual({ date: '2026-08-03', time: '15:30' })
  })

  it('reads midnight back as an unset time, so the field never shows an hour nobody typed', () => {
    expect(splitInstant('2026-08-03T00:00')).toEqual({ date: '2026-08-03', time: '' })
  })

  it('drops the seconds a `time` input would reject', () => {
    expect(splitInstant('2026-08-03T15:30:45')).toEqual({ date: '2026-08-03', time: '15:30' })
  })

  it('treats null/empty as both parts empty', () => {
    expect(splitInstant(null)).toEqual({ date: '', time: '' })
    expect(splitInstant('')).toEqual({ date: '', time: '' })
  })
})

describe('joinInstant', () => {
  it('composes midnight when the time is left out', () => {
    expect(joinInstant('2026-08-03', '')).toBe('2026-08-03T00:00')
  })

  it('composes the picked time when there is one', () => {
    expect(joinInstant('2026-08-03', '15:30')).toBe('2026-08-03T15:30')
  })

  it('has no instant at all without a date, whatever the time says', () => {
    expect(joinInstant('', '15:30')).toBeNull()
  })
})
