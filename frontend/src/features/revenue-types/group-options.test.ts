import { describe, expect, it } from 'vitest'
import { groupRevenueTypes, revenueTypeLabel } from '@/features/revenue-types/group-options'
import type { RevenueTypeOption } from '@/features/revenue-types/types'

function option(id: number, code: string, groupCode: string, accountCode: string): RevenueTypeOption {
  return {
    id,
    code,
    name: `Sub ${code}`,
    path: '',
    is_active: true,
    group: { code: groupCode, name: `Group ${groupCode}` },
    account: { code: accountCode, name: `Account ${accountCode}` },
  }
}

describe('groupRevenueTypes', () => {
  it('groups by group then account keeping the server order', () => {
    const groups = groupRevenueTypes([
      option(1, '70.01.001', '70', '70.01'),
      option(2, '70.01.002', '70', '70.01'),
      option(3, '70.02.001', '70', '70.02'),
      option(4, '71.01.001', '71', '71.01'),
    ])

    expect(groups.map((group) => group.heading)).toEqual(['70 Group 70', '71 Group 71'])
    expect(groups[0]?.accounts.map((account) => account.heading)).toEqual(['70.01 Account 70.01', '70.02 Account 70.02'])
    expect(groups[0]?.accounts[0]?.options.map((item) => item.id)).toEqual([1, 2])
  })

  it('returns no groups for no options', () => {
    expect(groupRevenueTypes([])).toEqual([])
  })

  it('labels an option with code first', () => {
    expect(revenueTypeLabel({ code: '70.01.001', name: 'Consulting' })).toBe('70.01.001 Consulting')
  })
})
