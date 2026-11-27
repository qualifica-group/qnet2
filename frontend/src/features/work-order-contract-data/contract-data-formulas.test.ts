import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { netAmountHint, revenueTotalHint } from '@/features/work-order-contract-data/contract-data-formulas'
import { PAID_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const t = i18n.t.bind(i18n)

describe('netAmountHint', () => {
  it('shows quantity times unit price', () => {
    expect(netAmountHint(PAID_LINE, t)).toEqual({ title: 'Amount', lines: ['Quantity × unit price.', '2 × 500.00 = 1,000.00'] })
  })
})

describe('revenueTotalHint', () => {
  it('is the total revenue rule only', () => {
    expect(revenueTotalHint(t)).toEqual({
      title: 'Total revenue',
      lines: ["Sum of the lines' effective revenue: the received commission, otherwise the net amount."],
    })
  })
})
