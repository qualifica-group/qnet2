import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { netAmountHint, totalsHint } from '@/features/work-order-contract-data/contract-data-formulas'
import { CONTRACT_DATA, PAID_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const t = i18n.t.bind(i18n)

describe('netAmountHint', () => {
  it('shows quantity times unit price', () => {
    expect(netAmountHint(PAID_LINE, t)).toEqual({ title: 'Amount', lines: ['Quantity × unit price.', '2 × 500.00 = 1,000.00'] })
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
