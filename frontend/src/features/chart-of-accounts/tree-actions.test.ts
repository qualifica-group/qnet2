import { describe, expect, it } from 'vitest'
import { ALL_ABILITIES, NO_ABILITIES, buildTree } from '@/features/chart-of-accounts/chart-tree-fixtures'
import { rowActions } from '@/features/chart-of-accounts/tree-actions'
import { buildRows } from '@/features/chart-of-accounts/tree-model'

const tree = buildTree({ unplaced_cost_types: [{ ...buildTree().groups[0].accounts[0].sub_accounts[0], id: 9, code: null }] })
const rows = buildRows(tree, new Set(['group:1', 'group:2', 'account:10', 'account:20', 'unplaced']), '')
const row = (key: string) => {
  const found = rows.find((r) => r.key === key)
  if (!found) throw new Error(`missing row ${key}`)
  return found
}

describe('rowActions (spec 0348 AC-013)', () => {
  it('gives a group the account creation, edit, history and delete actions', () => {
    expect(rowActions(row('group:1'), ALL_ABILITIES)).toEqual(['newAccount', 'edit', 'activity', 'delete'])
  })

  it('gates a new sub-account on the ability of its kind (cost types vs revenue types)', () => {
    const abilities = { ...ALL_ABILITIES, revenue_types: { ...ALL_ABILITIES.revenue_types, create: false } }

    expect(rowActions(row('account:10'), abilities)).toContain('newSubAccount')
    expect(rowActions(row('account:20'), abilities)).not.toContain('newSubAccount')
  })

  it('offers "place" instead of "edit" for an unplaced cost type', () => {
    expect(rowActions(row('cost_type:9'), ALL_ABILITIES)).toEqual(['place', 'activity', 'delete'])
  })

  it('hides every action without abilities, and the virtual group never has any', () => {
    expect(rowActions(row('group:1'), NO_ABILITIES)).toEqual([])
    expect(rowActions(row('cost_type:1'), NO_ABILITIES)).toEqual([])
    expect(rowActions(row('unplaced'), ALL_ABILITIES)).toEqual([])
  })
})
