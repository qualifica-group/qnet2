import { describe, expect, it } from 'vitest'
import { groupSubAccounts, subAccountLabel } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'

const COSTS = { code: '61', name: 'Costs' }

describe('groupSubAccounts', () => {
  it('groups by mastro then conto keeping the order and appends the unplaced ones last', () => {
    const groups = groupSubAccounts(
      [
        { id: 1, group: COSTS, account: { code: '61.01', name: 'Purchases' } },
        { id: 2, group: COSTS, account: { code: '61.01', name: 'Purchases' } },
        { id: 3, group: null, account: null },
        { id: 4, group: COSTS, account: { code: '61.02', name: 'Trips' } },
        { id: 5 },
      ],
      'To be placed',
    )

    expect(groups.map((group) => group.heading)).toEqual(['61 Costs', 'To be placed'])
    expect(groups[0]?.accounts.map((account) => account.options.map((item) => item.id))).toEqual([[1, 2], [4]])
    expect(groups[1]?.accounts).toEqual([{ key: '__unplaced__', heading: '', options: [{ id: 3, group: null, account: null }, { id: 5 }] }])
  })

  it('has no unplaced group when everything is placed, and no groups when empty', () => {
    expect(groupSubAccounts([{ id: 1, group: COSTS, account: { code: '61.01', name: 'P' } }], 'x')).toHaveLength(1)
    expect(groupSubAccounts([], 'x')).toEqual([])
  })
})

describe('subAccountLabel', () => {
  it('is code + name, or the name alone without a code', () => {
    expect(subAccountLabel({ code: '61.01.001', name: 'Fuel' })).toBe('61.01.001 Fuel')
    expect(subAccountLabel({ code: null, name: 'Fuel' })).toBe('Fuel')
    expect(subAccountLabel({ name: 'Fuel' })).toBe('Fuel')
  })
})
