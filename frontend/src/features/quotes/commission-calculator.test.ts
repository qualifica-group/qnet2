import { describe, expect, it } from 'vitest'
import {
  allocatedCostNetByOfferLineKey,
  calculateCommissionAmount,
  calculateCommissionBaseNet,
  calculateCommissionTotals,
  sumCommissionTotals,
} from './commission-calculator'

describe('commission calculator', () => {
  it('calculates percentage on the given base and fixed amount once per line', () => {
    expect(calculateCommissionAmount('PERCENTAGE', 10, 250)).toBe(25)
    expect(calculateCommissionAmount('FIXED_AMOUNT', 10, 250)).toBe(10)
  })

  it('aggregates all roles and keeps missing roles at zero (no imputed cost)', () => {
    expect(calculateCommissionTotals([{
      quantity: 2,
      unit_price: 100,
      commissions: [{ recipient_role: 'COMMERCIAL', commission_type: 'PERCENTAGE', value: 5 }],
    }])).toEqual({ commercial: 10, reporter: 0, supervisor: 0, supplier: 0 })
  })

  /**
   * Spec 0145 (D-1/AC-001): base = net riga - costi imputati alla riga.
   * Ricavo 1000, costo imputato 400, commissione 10% -> 60.00.
   */
  it('bases a PERCENTAGE commission on the row net minus its own imputed cost (D-1, AC-001)', () => {
    const totals = calculateCommissionTotals(
      [{
        quantity: 1,
        unit_price: 1000,
        client_key: 'offer-1',
        commissions: [{ recipient_role: 'COMMERCIAL', commission_type: 'PERCENTAGE', value: 10 }],
      }],
      [{ offer_line_key: 'offer-1', quantity: 1, unit_price: 400 }],
    )

    expect(totals.commercial).toBe(60)
  })

  /** Spec 0145 (AC-002): a GENERIC cost (no `offer_line_key`) never reduces any base. */
  it('does not let a generic (unassociated) cost reduce the base (D-1, AC-002)', () => {
    const totals = calculateCommissionTotals(
      [{
        quantity: 1,
        unit_price: 1000,
        client_key: 'offer-1',
        commissions: [{ recipient_role: 'COMMERCIAL', commission_type: 'PERCENTAGE', value: 10 }],
      }],
      [{ offer_line_key: null, quantity: 1, unit_price: 400 }],
    )

    expect(totals.commercial).toBe(100)
  })

  /** Spec 0145 (D-2/AC-003): imputed costs exceeding the row net clamp the PERCENTAGE base at zero. */
  it('clamps the base at zero when imputed costs exceed the row net (D-2, AC-003)', () => {
    expect(calculateCommissionBaseNet(100, 150)).toBe(0)
    const totals = calculateCommissionTotals(
      [{
        quantity: 1,
        unit_price: 100,
        client_key: 'offer-1',
        commissions: [
          { recipient_role: 'COMMERCIAL', commission_type: 'PERCENTAGE', value: 10 },
          { recipient_role: 'SUPPLIER', commission_type: 'FIXED_AMOUNT', value: 25 },
        ],
      }],
      [{ offer_line_key: 'offer-1', quantity: 1, unit_price: 150 }],
    )

    expect(totals.commercial).toBe(0)
    expect(totals.supplier).toBe(25)
  })

  it('sums several cost rows imputed to the same offer row', () => {
    const map = allocatedCostNetByOfferLineKey([
      { offer_line_key: 'a', quantity: 1, unit_price: 10 },
      { offer_line_key: 'a', quantity: 1, unit_price: 15 },
      { offer_line_key: null, quantity: 1, unit_price: 999 },
    ])

    expect(map.get('a')).toBe(25)
    expect(map.has('null')).toBe(false)
  })

  it('sums every role total', () => {
    expect(sumCommissionTotals({ commercial: 10, reporter: 5, supervisor: 0, supplier: 2.5 })).toBe(17.5)
  })
})
