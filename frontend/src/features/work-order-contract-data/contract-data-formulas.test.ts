import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  commissionHint,
  netAmountHint,
  netOfCommissionsHint,
  revenueHint,
  totalsHint,
} from '@/features/work-order-contract-data/contract-data-formulas'
import { CONTRACT_DATA, PAID_LINE, RECEIVED_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const t = i18n.t.bind(i18n)

describe('netAmountHint', () => {
  it('shows quantity times unit price', () => {
    expect(netAmountHint(PAID_LINE, t).lines).toEqual(['Quantity × unit price.', '2 × 500.00 = 1,000.00'])
  })
})

describe('commissionHint', () => {
  it('names the direction and the percentage with its base', () => {
    expect(commissionHint(PAID_LINE, t)).toEqual({
      title: 'Supplier commission paid',
      lines: ['15% of 1,000.00 = 150.00', 'The base is the line margin.'],
    })
  })

  it('shows a fixed commission without a base', () => {
    const line: ContractDataLine = {
      ...RECEIVED_LINE,
      supplier_commission: { commission_type: 'FIXED_AMOUNT', value: '300.0000', base_amount: null, amount: '300.00', is_stale: false },
    }
    expect(commissionHint(line, t)).toEqual({ title: 'Supplier commission received', lines: ['Fixed amount: 300.00'] })
  })

  it('is absent without a commission', () => {
    expect(commissionHint({ ...PAID_LINE, supplier_commission: null }, t)).toBeNull()
  })
})

describe('netOfCommissionsHint', () => {
  it('shows net minus commissions equals result', () => {
    expect(netOfCommissionsHint(PAID_LINE, t)?.lines[1]).toBe('1,000.00 − 250.00 = 750.00')
  })

  it('is absent when the commissions are hidden', () => {
    expect(netOfCommissionsHint({ ...PAID_LINE, commissions_amount: null, net_of_commissions: null }, t)).toBeNull()
  })
})

describe('revenueHint (spec 0202 D-13)', () => {
  it('explains a line without a Supplier commission from the net amount', () => {
    const line: ContractDataLine = { ...PAID_LINE, supplier_commission_direction: null, supplier_commission: null }
    expect(revenueHint(line, true, t).lines).toEqual([
      'Supplier commission not calculated: the revenue is the net amount.',
      '2 × 500.00 = 1,000.00 → revenue 1,000.00',
    ])
  })

  it('explains a PAID commission as a cost beside the net amount', () => {
    expect(revenueHint(PAID_LINE, true, t).lines[0]).toBe(
      'Supplier commission paid: the revenue is the net amount, the commission is a cost.',
    )
  })

  it('explains a RECEIVED percentage commission as the revenue', () => {
    expect(revenueHint(RECEIVED_LINE, true, t).lines).toEqual([
      'Supplier commission received: the revenue is the commission, not the net amount.',
      '10% of 2,000.00 = 200.00 → revenue 200.00',
    ])
  })

  it('says so when a RECEIVED line has no Supplier commission', () => {
    const line: ContractDataLine = { ...RECEIVED_LINE, supplier_commission: null, effective_revenue: '0.00' }
    expect(revenueHint(line, true, t).lines[1]).toBe('Commission missing → revenue 0.00')
  })

  it('reveals no commission detail on a RECEIVED line when commissions are not visible', () => {
    const line: ContractDataLine = { ...RECEIVED_LINE, supplier_commission: null }
    expect(revenueHint(line, false, t).lines[1]).toBe('Revenue 200.00')
  })
})

describe('totalsHint', () => {
  it('shows the net-of-commissions formula with the totals', () => {
    expect(totalsHint('netOfCommissions', CONTRACT_DATA.totals, t).lines[1]).toBe('3,000.00 − 450.00 = 2,550.00')
  })

  it('is a rule only for the other KPIs', () => {
    expect(totalsHint('revenue', CONTRACT_DATA.totals, t).lines).toHaveLength(1)
  })
})
