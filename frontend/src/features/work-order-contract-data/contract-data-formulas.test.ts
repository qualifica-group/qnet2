import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { netOfCommissionsFormula, revenueFormula } from '@/features/work-order-contract-data/contract-data-formulas'
import { PAID_LINE, RECEIVED_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const t = i18n.t.bind(i18n)

describe('revenueFormula (AC-016, spec 0202 D-13)', () => {
  it('explains a line without Supplier commission from quantity, unit price and net amount', () => {
    const line: ContractDataLine = {
      ...PAID_LINE,
      supplier_commission_direction: null,
      supplier_commission: null,
    }
    expect(revenueFormula(line, true, t)).toBe('Net amount 2 × 500.00 = 1,000.00 → revenue 1,000.00')
  })

  it('explains a PAID Supplier commission as a cost next to the net amount', () => {
    expect(revenueFormula(PAID_LINE, true, t)).toBe(
      'Net amount 2 × 500.00 = 1,000.00 → revenue 1,000.00 · Supplier commission paid: 15% of 1,000.00 = 150.00 (a cost, it does not reduce the revenue)',
    )
  })

  it('shows only the net amount for a PAID line when commissions are hidden', () => {
    expect(revenueFormula({ ...PAID_LINE, supplier_commission: null }, false, t)).toBe(
      'Net amount 2 × 500.00 = 1,000.00 → revenue 1,000.00',
    )
  })

  it('explains a percentage RECEIVED Supplier commission as the revenue', () => {
    expect(revenueFormula(RECEIVED_LINE, true, t)).toBe(
      'Supplier commission received: 10% of 2,000.00 = 200.00 → revenue 200.00',
    )
  })

  it('explains a fixed RECEIVED Supplier commission without a base', () => {
    const line: ContractDataLine = {
      ...RECEIVED_LINE,
      supplier_commission: { commission_type: 'FIXED_AMOUNT', value: '300.0000', base_amount: null, amount: '300.00', is_stale: false },
      effective_revenue: '300.00',
    }
    expect(revenueFormula(line, true, t)).toBe('Supplier commission received: fixed 300.00 → revenue 300.00')
  })

  it('says so when a RECEIVED line has no Supplier commission', () => {
    const line: ContractDataLine = { ...RECEIVED_LINE, supplier_commission: null, effective_revenue: '0.00' }
    expect(revenueFormula(line, true, t)).toBe('Supplier commission received missing → revenue 0.00')
  })

  it('reveals no commission detail on a RECEIVED line when commissions are not visible', () => {
    const line: ContractDataLine = { ...RECEIVED_LINE, supplier_commission: null }
    expect(revenueFormula(line, false, t)).toBe('Supplier commission received: revenue 200.00')
  })
})

describe('netOfCommissionsFormula', () => {
  it('shows net minus commissions equals result', () => {
    expect(netOfCommissionsFormula(PAID_LINE, t)).toBe('Net of commissions: 1,000.00 − 250.00 = 750.00')
  })

  it('is absent when the commissions are hidden', () => {
    expect(netOfCommissionsFormula({ ...PAID_LINE, commissions_amount: null, net_of_commissions: null }, t)).toBeNull()
  })
})
