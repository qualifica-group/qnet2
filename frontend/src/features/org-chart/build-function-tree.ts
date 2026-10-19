import {
  UNASSIGNED_KEY,
  byName,
  childKey,
  personSearchText,
  type FunctionNode,
  type OrgNodeRef,
  type OrgTreeNode,
  type PersonNode,
} from '@/features/org-chart/tree-nodes'
import type { OrgChartFunctions, OrgPerson } from '@/features/org-chart/types'

function toPersonNode(person: OrgPerson, parentKey: string, parent: OrgNodeRef, isFunctionHead: boolean): PersonNode {
  return {
    kind: 'person',
    id: person.id,
    key: childKey(parentKey, `p:${person.id}`),
    parent,
    person,
    isResponsible: person.is_manager || isFunctionHead,
    functions: [],
    searchText: personSearchText(person, []),
    children: [],
  }
}

/**
 * Builds the Functions tree from the flat list (`parent_id`). A function whose
 * parent is not in the list is a root. Under each function: sub-functions
 * first, then its people. The "all functions" group, when it has people, is
 * appended as the last root. The ancestor `path` guards against a cycle in the
 * data.
 */
export function buildFunctionTree(data: OrgChartFunctions): OrgTreeNode[] {
  const ids = new Set(data.functions.map((fn) => fn.id))
  const childrenOf = new Map<number, OrgChartFunctions['functions']>()
  data.functions.forEach((fn) => {
    if (fn.parent_id === null || !ids.has(fn.parent_id)) return
    childrenOf.set(fn.parent_id, [...(childrenOf.get(fn.parent_id) ?? []), fn])
  })

  function toNode(
    fn: OrgChartFunctions['functions'][number],
    parentKey: string | null,
    parent: OrgNodeRef | null,
    path: ReadonlySet<number>,
  ): FunctionNode {
    const key = childKey(parentKey, `f:${fn.id}`)
    const nextPath = new Set(path).add(fn.id)
    const self: OrgNodeRef = { kind: 'function', id: fn.id }
    const subFunctions = (childrenOf.get(fn.id) ?? [])
      .filter((child) => !nextPath.has(child.id))
      .sort(byName)
      .map((child) => toNode(child, key, self, nextPath))
    const people = fn.people.map((person) => toPersonNode(person, key, self, fn.manager?.id === person.id))
    return {
      kind: 'function',
      id: fn.id,
      key,
      parent,
      businessFunction: fn,
      searchText: [fn.name, fn.manager?.name ?? ''].join(' ').toLowerCase(),
      children: [...subFunctions, ...people],
    }
  }

  const roots = data.functions
    .filter((fn) => fn.parent_id === null || !ids.has(fn.parent_id))
    .sort(byName)
    .map((fn) => toNode(fn, null, null, new Set()))

  if (data.unassigned_people.length === 0) return roots

  const allRef: OrgNodeRef = { kind: 'unassigned', id: null }
  return [
    ...roots,
    {
      kind: 'unassigned',
      id: null,
      key: UNASSIGNED_KEY,
      parent: null,
      peopleCount: data.unassigned_people.length,
      searchText: '',
      children: data.unassigned_people.map((person) => toPersonNode(person, UNASSIGNED_KEY, allRef, false)),
    },
  ]
}
