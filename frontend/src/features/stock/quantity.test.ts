import { describe, expect, it } from 'vitest'
import { formatQuantity, isPositiveQuantity, isValidQuantity, toWireQuantity } from '@/features/stock/quantity'

describe('stock quantity helpers (spec 0307 D-10)', () => {
  it.each([
    ['3.000', '3'],
    ['2.500', '2.5'],
    ['10.000', '10'],
    ['0.125', '0.125'],
    ['7', '7'],
  ])('formats %s as %s', (value, expected) => {
    expect(formatQuantity(value)).toBe(expected)
  })

  it.each(['1', '1.5', '1,5', '0', '12.345'])('accepts %s', (value) => {
    expect(isValidQuantity(value)).toBe(true)
  })

  it.each(['', 'abc', '-1', '1.2345', '1.', '.5'])('rejects %s', (value) => {
    expect(isValidQuantity(value)).toBe(false)
  })

  it('treats zero as valid but not positive', () => {
    expect(isValidQuantity('0')).toBe(true)
    expect(isPositiveQuantity('0')).toBe(false)
    expect(isPositiveQuantity('0,001')).toBe(true)
  })

  it('sends a dot as the decimal separator', () => {
    expect(toWireQuantity(' 2,5 ')).toBe('2.5')
  })
})
