import { describe, expect, it } from 'vitest'
import { calculateCommissionAmount, calculateCommissionTotals } from './commission-calculator'

describe('commission calculator', () => {
  it('calculates percentage on line net and fixed amount once per line', () => {
    expect(calculateCommissionAmount('PERCENTAGE', 10, 250)).toBe(25)
    expect(calculateCommissionAmount('FIXED_AMOUNT', 10, 250)).toBe(10)
  })

  it('aggregates all roles and keeps missing roles at zero', () => {
    expect(calculateCommissionTotals([{
      quantity: 2,
      unit_price: 100,
      commissions: [{ recipient_role: 'COMMERCIAL', commission_type: 'PERCENTAGE', value: 5 }],
    }])).toEqual({ commercial: 10, reporter: 0, supervisor: 0, supplier: 0 })
  })
})
