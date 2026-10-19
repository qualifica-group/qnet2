/**
 * Node model shared by the two org-chart trees (spec 0273 D-2/D-3). The trees
 * are built from the flat API lists (`build-function-tree.ts`,
 * `build-people-tree.ts`) and rendered by the generic `OrgTree`.
 *
 * `key` is a PATH (`parentKey/segment`), not the bare id: a person with several
 * managers appears under each of them and each occurrence keeps its own
 * expand/collapse state. `parent` identifies the node the row sits under (the
 * "from" side of a move: manager person id or function id), `null` for roots.
 */

import type { OrgDirectoryPerson, OrgFunction, OrgPerson, OrgPersonFunction } from '@/features/org-chart/types'

export type OrgNodeKind = 'function' | 'person' | 'unassigned'

export interface OrgNodeRef {
  kind: OrgNodeKind
  /** `null` for the synthetic "all functions" group. */
  id: number | null
}

interface OrgNodeBase {
  key: string
  parent: OrgNodeRef | null
  /** Lower-cased text matched by the search box. */
  searchText: string
  children: OrgTreeNode[]
}

export interface FunctionNode extends OrgNodeBase {
  kind: 'function'
  id: number
  businessFunction: OrgFunction
}

export interface PersonNode extends OrgNodeBase {
  kind: 'person'
  id: number
  person: OrgPerson | OrgDirectoryPerson
  /** Crown: the person is flagged manager, or heads the function they sit under. */
  isResponsible: boolean
  /** Names of the person's business functions (people view only). */
  functions: OrgPersonFunction[]
}

export interface UnassignedNode extends OrgNodeBase {
  kind: 'unassigned'
  id: null
  peopleCount: number
}

export type OrgTreeNode = FunctionNode | PersonNode | UnassignedNode

export const UNASSIGNED_KEY = 'unassigned'

export function childKey(parentKey: string | null, segment: string): string {
  return parentKey ? `${parentKey}/${segment}` : segment
}

export function byName(a: { name: string }, b: { name: string }): number {
  return a.name.localeCompare(b.name)
}

/**
 * Children drawn as cards in the chart (spec 0274 D-2/D-3). In the People view
 * every child is a card; under a function (or "all functions") the people are
 * not cards but members listed in the side panel, see `memberPeople`.
 */
export function chartChildren(node: OrgTreeNode): OrgTreeNode[] {
  return node.kind === 'person' ? node.children : node.children.filter((child) => child.kind !== 'person')
}

/** People of a function / "all functions" card, shown in its side panel. */
export function memberPeople(node: OrgTreeNode): PersonNode[] {
  if (node.kind === 'person') return []
  return node.children.filter((child): child is PersonNode => child.kind === 'person')
}

export function personSearchText(person: OrgPerson, functionNames: readonly string[]): string {
  return [person.name, ...person.job_roles, ...functionNames].join(' ').toLowerCase()
}
