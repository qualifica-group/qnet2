import { describe, expect, it } from 'vitest'
import { buildTree, costSub } from '@/features/chart-of-accounts/chart-tree-fixtures'
import {
  UNPLACED_KEY,
  accountOptions,
  buildRows,
  defaultExpandedKeys,
  expandableKeys,
} from '@/features/chart-of-accounts/tree-model'

const keys = (rows: ReturnType<typeof buildRows>) => rows.map((row) => row.key)

describe('tree-model (spec 0348)', () => {
  it('opens mastri by default and keeps conti closed', () => {
    const tree = buildTree()
    const rows = buildRows(tree, defaultExpandedKeys(tree), '')

    expect(keys(rows)).toEqual(['group:1', 'account:10', 'account:11', 'group:2', 'account:20'])
  })

  it('shows sub-accounts of an expanded account at depth 2', () => {
    const tree = buildTree()
    const expanded = new Set([...defaultExpandedKeys(tree), 'account:10'])
    const rows = buildRows(tree, expanded, '')

    expect(rows.find((row) => row.key === 'cost_type:1')?.depth).toBe(2)
  })

  it('lists the unplaced group only when there are unplaced cost types', () => {
    expect(keys(buildRows(buildTree(), new Set(), '')).includes(UNPLACED_KEY)).toBe(false)

    const tree = buildTree({ unplaced_cost_types: [costSub({ id: 9, code: null, name: 'Altro legacy' })] })
    const rows = buildRows(tree, defaultExpandedKeys(tree), '')

    expect(keys(rows).slice(-2)).toEqual([UNPLACED_KEY, 'cost_type:9'])
  })

  it('searches by code or description, showing matches with their ancestors open', () => {
    const tree = buildTree()

    expect(keys(buildRows(tree, new Set(), '61.03.001'))).toEqual(['group:1', 'account:10', 'cost_type:1'])
    expect(keys(buildRows(tree, new Set(), 'LUCE'))).toEqual(['group:1', 'account:10'])
    expect(keys(buildRows(tree, new Set(), 'consulenza'))).toEqual(['group:2', 'account:20', 'revenue_type:5'])
    expect(buildRows(tree, new Set(), 'zzz')).toEqual([])
  })

  it('ignores the expanded set while searching, so clearing the search restores it', () => {
    const tree = buildTree()
    const collapsed = new Set<string>()

    expect(keys(buildRows(tree, collapsed, ''))).toEqual(['group:1', 'group:2'])
    expect(keys(buildRows(tree, collapsed, 'utenze'))).toContain('cost_type:1')
    expect(keys(buildRows(tree, collapsed, '  '))).toEqual(['group:1', 'group:2'])
  })

  it('lists every node with children for expand all', () => {
    const tree = buildTree({ unplaced_cost_types: [costSub({ id: 9 })] })

    expect(expandableKeys(tree)).toEqual(['group:1', 'account:10', 'group:2', 'account:20', UNPLACED_KEY])
  })

  it('offers only the conti of the requested kind as parents', () => {
    expect(accountOptions(buildTree().groups, 'revenue')).toEqual([{ value: '20', label: '70.01 Prestazioni di servizi' }])
  })
})
