import { describe, expect, it } from 'vitest'
import { buildFunctionTree } from '@/features/org-chart/build-function-tree'
import { buildPeopleTree } from '@/features/org-chart/build-people-tree'
import { FUNCTIONS_FIXTURE, PEOPLE_FIXTURE } from '@/features/org-chart/fixtures.test-support'
import {
  buildPendingMove,
  findNode,
  getMoveDestinations,
  isMovable,
  resolveDrop,
} from '@/features/org-chart/move-destinations'

const peopleTree = buildPeopleTree(PEOPLE_FIXTURE)
const functionTree = buildFunctionTree(FUNCTIONS_FIXTURE)

function personNode(key: string) {
  const find = (nodes: typeof peopleTree): (typeof peopleTree)[number] | null => {
    for (const node of nodes) {
      if (node.key === key) return node
      const found = find(node.children)
      if (found) return found
    }
    return null
  }
  const node = find(peopleTree) ?? find(functionTree)
  if (!node) throw new Error(`no node ${key}`)
  return node
}

const names = (list: { name: string; kind: string }[]) => list.map((d) => (d.kind === 'root' ? '<root>' : d.name))

describe('getMoveDestinations - people view', () => {
  it('excludes the subject, its descendants, its current manager and the managers it already has', () => {
    const bruno = personNode('p:1/p:2')
    expect(names(getMoveDestinations('people', bruno, peopleTree))).toEqual(['<root>', 'Dario Fiore'])
  })

  it('a leaf can go under any other person and offers root only when it is not already a root', () => {
    const dario = personNode('p:4')
    expect(names(getMoveDestinations('people', dario, peopleTree))).toEqual(['Anna Rossi', 'Bruno Neri', 'Carla Gallo'])
    const carla = personNode('p:1/p:3')
    expect(names(getMoveDestinations('people', carla, peopleTree))[0]).toBe('<root>')
  })

  it('computes descendants on the full tree even when the row comes from a filtered subset', () => {
    const filteredBruno = { ...personNode('p:1/p:2'), children: [] }
    expect(names(getMoveDestinations('people', filteredBruno, peopleTree))).not.toContain('Carla Gallo')
  })

  it('a manager (is_manager) can only become a root, and a root manager has no move', () => {
    const managerUnderSomeone = buildPeopleTree([
      { ...PEOPLE_FIXTURE[0], manager_ids: [4] },
      PEOPLE_FIXTURE[3],
    ])[0].children[0]
    expect(names(getMoveDestinations('people', managerUnderSomeone, [...peopleTree]))).toEqual(['<root>'])
    const anna = personNode('p:1')
    expect(isMovable('people', anna)).toBe(false)
    expect(getMoveDestinations('people', anna, peopleTree)).toEqual([])
  })
})

describe('getMoveDestinations - functions view', () => {
  it('a function can go to root or under functions that are not itself, its descendants or its parent', () => {
    const sales = personNode('f:1/f:2')
    expect(names(getMoveDestinations('functions', sales, functionTree))).toEqual(['<root>', 'Support'])
    const company = personNode('f:1')
    expect(getMoveDestinations('functions', company, functionTree)).toEqual([])
  })

  it('never offers a descendant function (cycle)', () => {
    const company = { ...personNode('f:1'), parent: { kind: 'function' as const, id: 99 } }
    expect(names(getMoveDestinations('functions', company, functionTree))).toEqual(['<root>'])
  })

  it('a person can go to any function but their own', () => {
    const carla = personNode('f:1/f:2/p:12')
    expect(names(getMoveDestinations('functions', carla, functionTree))).toEqual(['Company', 'Support'])
  })

  it('the all-functions group and people covering all functions are not movable', () => {
    expect(isMovable('functions', personNode('all'))).toBe(false)
    const dario = personNode('all/p:13')
    expect(isMovable('functions', dario)).toBe(false)
    expect(getMoveDestinations('functions', dario, functionTree)).toEqual([])
  })
})

describe('resolveDrop', () => {
  it('accepts only rows that are a valid destination of the same kind', () => {
    const dario = personNode('p:4')
    const destinations = getMoveDestinations('people', dario, peopleTree)
    expect(resolveDrop(destinations, personNode('p:1/p:2'))?.id).toBe(2)
    expect(resolveDrop(destinations, personNode('p:4'))).toBeNull()
    expect(resolveDrop(destinations, personNode('f:1'))).toBeNull()
    expect(resolveDrop(destinations, personNode('all'))).toBeNull()
  })

  it('rejects a drop of a function on one of its descendants', () => {
    const company = { ...personNode('f:1'), parent: { kind: 'function' as const, id: 99 } }
    const destinations = getMoveDestinations('functions', company, functionTree)
    expect(resolveDrop(destinations, personNode('f:1/f:2'))).toBeNull()
  })
})

describe('buildPendingMove', () => {
  it('shapes the three move payloads with from/to names', () => {
    const carla = personNode('p:1/p:3')
    const toDario = { kind: 'person' as const, id: 4, name: 'Dario Fiore', depth: 0 }
    expect(buildPendingMove('people', carla, toDario, peopleTree)).toMatchObject({
      move: { type: 'manager', userId: 3, fromId: 1, toId: 4 },
      fromName: 'Anna Rossi',
      toName: 'Dario Fiore',
    })
    const root = { kind: 'root' as const, id: null, name: '', depth: 0 }
    expect(buildPendingMove('people', carla, root, peopleTree).move).toEqual({ type: 'manager', userId: 3, fromId: 1, toId: null })

    const sales = personNode('f:1/f:2')
    expect(buildPendingMove('functions', sales, root, functionTree)).toMatchObject({
      move: { type: 'parent', functionId: 2, toId: null },
      toName: null,
    })

    const carlaInSales = personNode('f:1/f:2/p:12')
    const toSupport = { kind: 'function' as const, id: 3, name: 'Support', depth: 1 }
    expect(buildPendingMove('functions', carlaInSales, toSupport, functionTree)).toMatchObject({
      move: { type: 'function', userId: 12, fromId: 2, toId: 3 },
      fromName: 'Sales',
    })
  })

  it('findNode locates a node by kind and id', () => {
    expect(findNode(functionTree, { kind: 'function', id: 3 })?.key).toBe('f:1/f:3')
  })
})
