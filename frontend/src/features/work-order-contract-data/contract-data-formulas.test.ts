import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { netOfCommissionsFormula, revenueFormula } from '@/features/work-order-contract-data/contract-data-formulas'
import { CONSULTANCY_LINE, INSTITUTION_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const t = i18n.t.bind(i18n)

describe('revenueFormula (AC-016)', () => {
  it('explains a consulting line from quantity, unit price and net amount', () => {
    expect(revenueFormula(CONSULTANCY_LINE, true, t)).toBe('Net amount 2 × 500.00 = 1,000.00 → revenue 1,000.00')
  })

  it('explains a percentage Supplier commission on an institution line', () => {
    expect(revenueFormula(INSTITUTION_LINE, true, t)).toBe(
      'Supplier commission 10% of 2,000.00 = 200.00 → revenue 200.00',
    )
  })

  it('explains a fixed Supplier commission without a base', () => {
    const line: ContractDataLine = {
      ...INSTITUTION_LINE,
      supplier_commission: { commission_type: 'FIXED_AMOUNT', value: '300.0000', base_amount: null, amount: '300.00', is_stale: false },
      effective_revenue: '300.00',
    }
    expect(revenueFormula(line, true, t)).toBe('Fixed Supplier commission 300.00 → revenue 300.00')
  })

  it('says so when an institution line has no Supplier commission', () => {
    const line: ContractDataLine = { ...INSTITUTION_LINE, supplier_commission: null, effective_revenue: '0.00' }
    expect(revenueFormula(line, true, t)).toBe('No Supplier commission → revenue 0.00')
  })

  it('reveals no commission detail when commissions are not visible', () => {
    const line: ContractDataLine = { ...INSTITUTION_LINE, supplier_commission: null }
    expect(revenueFormula(line, false, t)).toBe('Institution revenue 200.00')
  })
})

describe('netOfCommissionsFormula', () => {
  it('shows net minus commissions equals result', () => {
    expect(netOfCommissionsFormula(CONSULTANCY_LINE, t)).toBe('Net of commissions: 1,000.00 − 250.00 = 750.00')
  })

  it('is absent when the commissions are hidden', () => {
    expect(netOfCommissionsFormula({ ...CONSULTANCY_LINE, commissions_amount: null, net_of_commissions: null }, t)).toBeNull()
  })
})
