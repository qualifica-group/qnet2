import { describe, expect, it } from 'vitest'
import { computeCompensation, stringToCents } from '@/features/compensations/compensation-math'

describe('compensation math', () => {
  it('converts decimal strings to integer cents without float drift', () => {
    expect(stringToCents('1234.56')).toBe(123456)
    expect(stringToCents('0.1')).toBe(10)
    expect(stringToCents('300')).toBe(30000)
    expect(stringToCents('19.99')).toBe(1999)
  })

  it('AC-011 uses the minor residual and flags differing residuals', () => {
    expect(computeCompensation('500.00', '300.00')).toEqual({ amountCents: 30000, residualDiffers: true })
    expect(computeCompensation('300.00', '300.00')).toEqual({ amountCents: 30000, residualDiffers: false })
    expect(computeCompensation(null, '300.00')).toEqual({ amountCents: 0, residualDiffers: false })
  })
})
