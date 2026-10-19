import { describe, expect, it } from 'vitest'
import { buildFunctionTree } from '@/features/org-chart/build-function-tree'
import { buildPeopleTree } from '@/features/org-chart/build-people-tree'
import { directoryPerson, FUNCTIONS_FIXTURE, PEOPLE_FIXTURE } from '@/features/org-chart/fixtures.test-support'
import { collectExpandableKeys, filterTree, flattenVisible } from '@/features/org-chart/tree-utils'

describe('buildFunctionTree', () => {
  const tree = buildFunctionTree(FUNCTIONS_FIXTURE)

  it('nests functions by parent_id with sub-functions before people, plus the unassigned group last', () => {
    expect(tree.map((node) => node.key)).toEqual(['f:1', 'unassigned'])
    expect(tree[0].children.map((node) => node.key)).toEqual(['f:1/f:2', 'f:1/f:3', 'f:1/p:10'])
    expect(tree[0].children[0].children.map((node) => node.key)).toEqual(['f:1/f:2/p:11', 'f:1/f:2/p:12'])
    expect(tree[1].children.map((node) => node.key)).toEqual(['unassigned/p:13'])
  })

  it('records the parent reference of every node (from side of a move)', () => {
    const [company] = tree
    expect(company.parent).toBeNull()
    expect(company.children[0].parent).toEqual({ kind: 'function', id: 1 })
    expect(company.children[0].children[0].parent).toEqual({ kind: 'function', id: 2 })
    expect(tree[1].children[0].parent).toEqual({ kind: 'unassigned', id: null })
  })

  it('flags the function manager and flagged managers as responsible', () => {
    const sales = tree[0].children[0].children
    expect(sales.map((node) => node.kind === 'person' && node.isResponsible)).toEqual([true, false])
  })

  it('treats a function with an unknown parent as a root and omits an empty unassigned group', () => {
    const result = buildFunctionTree({
      functions: [{ id: 5, name: 'Orphan', type: null, parent_id: 404, manager: null, people: [] }],
      unassigned_people: [],
    })
    expect(result.map((node) => node.key)).toEqual(['f:5'])
  })

  it('does not loop on a cycle in the data', () => {
    const result = buildFunctionTree({
      functions: [
        { id: 1, name: 'A', type: null, parent_id: 2, manager: null, people: [] },
        { id: 2, name: 'B', type: null, parent_id: 1, manager: null, people: [] },
      ],
      unassigned_people: [],
    })
    expect(result).toEqual([])
  })
})

describe('buildPeopleTree', () => {
  const tree = buildPeopleTree(PEOPLE_FIXTURE)

  it('uses people without a listed manager as roots', () => {
    expect(tree.map((node) => node.key)).toEqual(['p:1', 'p:4'])
  })

  it('shows a person with several managers under each of them, with its own path key', () => {
    const [anna] = tree
    expect(anna.children.map((node) => node.key)).toEqual(['p:1/p:2', 'p:1/p:3'])
    expect(anna.children[0].children.map((node) => node.key)).toEqual(['p:1/p:2/p:3'])
    expect(anna.children[1].parent).toEqual({ kind: 'person', id: 1 })
    expect(anna.children[0].children[0].parent).toEqual({ kind: 'person', id: 2 })
  })

  it('stops at a reporting cycle', () => {
    const result = buildPeopleTree([directoryPerson(1, 'A', [2]), directoryPerson(2, 'B', [1]), directoryPerson(3, 'C')])
    expect(result.map((node) => node.key)).toEqual(['p:3'])
  })
})

describe('tree utils', () => {
  const tree = buildPeopleTree(PEOPLE_FIXTURE)

  it('keeps matches with their subtree and the ancestors of deeper matches', () => {
    const result = filterTree(tree, 'carla')
    expect(result.map((node) => node.key)).toEqual(['p:1'])
    expect(result[0].children.map((node) => node.key)).toEqual(['p:1/p:2', 'p:1/p:3'])
    expect(filterTree(tree, 'zzz')).toEqual([])
    expect(filterTree(tree, '  ')).toBe(tree)
  })

  it('matches on job role and function names', () => {
    expect(filterTree(tree, 'account')[0].children.map((node) => node.key)).toEqual(['p:1/p:2'])
    expect(filterTree(tree, 'company').map((node) => node.key)).toEqual(['p:1'])
  })

  it('lists expandable keys and the visible rows with their parent key', () => {
    expect(collectExpandableKeys(tree)).toEqual(['p:1', 'p:1/p:2'])
    const visible = flattenVisible(tree, new Set(['p:1']))
    expect(visible.map((entry) => entry.node.key)).toEqual(['p:1', 'p:4'])
    expect(flattenVisible(tree, new Set())[1]).toMatchObject({ parentKey: 'p:1' })
  })
})
