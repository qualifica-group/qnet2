/**
 * Pure tree/search/filter helpers for the team view (spec 0122 D-10/AC-038,
 * multi-manager 0166 D-7/AC-013). No React: `use-team-filters.ts` and
 * `time-entries-team-pulse.tsx` compose these over the `TeamPulse.items`
 * fetched by `use-time-entries-team.ts`. A member can have several managers
 * (`manager_ids`): it is built as a child of EVERY manager present in the
 * list and appears once per branch, so node `key` is a PATH (`parentKey/
 * userId`, roots just `userId`) rather than the bare user id — the same user
 * can occupy several nodes and each keeps its own independent expand/collapse
 * state. A member is a root only if NONE of its `manager_ids` is in the list.
 * Descending stops at a user already on the current path (ancestor guard)
 * so a cycle in the data can never loop the traversal.
 */

import { TIME_ENTRY_COVERAGE_MAX_PERCENTAGE } from '@/features/time-entries/time-entry-constants'
import type { TeamPulseMember } from '@/features/time-entries/types'

export interface TeamTreeNode {
  key: string
  member: TeamPulseMember
  children: TeamTreeNode[]
}

export type TeamActivityFilter = 'all' | 'active' | 'inactive'

export interface TeamFiltersValue {
  roles: string[]
  businessFunctions: string[]
  operationalSites: string[]
  activity: TeamActivityFilter
  coverageRange: [number, number]
}

export interface TeamOption {
  value: string
  label: string
}

function byMemberName(a: TeamPulseMember, b: TeamPulseMember): number {
  return a.user.name.localeCompare(b.user.name)
}

/**
 * Builds the manager/subordinate tree, each level sorted by name. A member
 * with several managers present in the list is attached under each of them
 * (own branch, own path key); it is a root only if none of its managers is
 * in the list. The ancestor `path` stops the walk from re-entering a user
 * already visited on the current branch (cycle guard).
 */
export function buildTeamTree(members: TeamPulseMember[]): TeamTreeNode[] {
  const byId = new Map(members.map((member) => [member.user.id, member]))
  const childrenByManager = new Map<number, TeamPulseMember[]>()

  members.forEach((member) => {
    member.manager_ids.forEach((managerId) => {
      if (!byId.has(managerId)) {
        return
      }
      const siblings = childrenByManager.get(managerId) ?? []
      siblings.push(member)
      childrenByManager.set(managerId, siblings)
    })
  })

  const roots = members.filter((member) => !member.manager_ids.some((managerId) => byId.has(managerId)))

  function toNode(member: TeamPulseMember, parentKey: string | null, path: ReadonlySet<number>): TeamTreeNode {
    const key = parentKey ? `${parentKey}/${member.user.id}` : String(member.user.id)
    const nextPath = new Set(path)
    nextPath.add(member.user.id)
    const children = (childrenByManager.get(member.user.id) ?? [])
      .filter((child) => !nextPath.has(child.user.id))
      .slice()
      .sort(byMemberName)
    return { key, member, children: children.map((child) => toNode(child, key, nextPath)) }
  }

  return roots
    .slice()
    .sort(byMemberName)
    .map((member) => toNode(member, null, new Set()))
}

/** Keys of every node with at least one child — the expand/collapse-all universe. */
export function collectExpandableKeys(nodes: TeamTreeNode[]): string[] {
  const keys: string[] = []
  const walk = (list: TeamTreeNode[]) => {
    list.forEach((node) => {
      if (node.children.length > 0) {
        keys.push(node.key)
        walk(node.children)
      }
    })
  }
  walk(nodes)
  return keys
}

function memberSearchHaystack(member: TeamPulseMember): string[] {
  return [
    member.user.name,
    member.user.email,
    member.job_description ?? '',
    member.operational_site?.label ?? '',
    ...member.roles,
    ...member.business_functions.map((businessFunction) => businessFunction.name),
  ]
}

export function memberMatchesSearch(member: TeamPulseMember, search: string): boolean {
  const needle = search.trim().toLowerCase()
  if (!needle) {
    return true
  }
  return memberSearchHaystack(member).some((value) => value.toLowerCase().includes(needle))
}

/**
 * Filters `members` by name/role/email/etc, then adds back every ancestor
 * (walking ALL `manager_ids` chains, so a matched descendant stays reachable
 * under every one of its managers) and every descendant (so a matched
 * manager still shows their whole branch) — AC-038/AC-013. A `visited` guard
 * on both walks keeps a data cycle from looping.
 */
export function filterMembersKeepingAncestorsAndDescendants(
  members: TeamPulseMember[],
  search: string,
): TeamPulseMember[] {
  const trimmed = search.trim()
  if (!trimmed) {
    return members
  }

  const byId = new Map(members.map((member) => [member.user.id, member]))
  const childrenByManager = new Map<number, TeamPulseMember[]>()
  members.forEach((member) => {
    member.manager_ids.forEach((managerId) => {
      const siblings = childrenByManager.get(managerId) ?? []
      siblings.push(member)
      childrenByManager.set(managerId, siblings)
    })
  })

  const visible = new Set<number>()

  function addDescendants(rootId: number): void {
    const stack = [rootId]
    while (stack.length > 0) {
      const current = stack.pop() as number
      for (const child of childrenByManager.get(current) ?? []) {
        if (visible.has(child.user.id)) {
          continue
        }
        visible.add(child.user.id)
        stack.push(child.user.id)
      }
    }
  }

  function addAncestors(memberId: number): void {
    const visited = new Set<number>([memberId])
    const stack = [memberId]
    while (stack.length > 0) {
      const current = stack.pop() as number
      for (const managerId of byId.get(current)?.manager_ids ?? []) {
        if (visited.has(managerId)) {
          continue
        }
        visited.add(managerId)
        visible.add(managerId)
        stack.push(managerId)
      }
    }
  }

  members.forEach((member) => {
    if (!memberMatchesSearch(member, trimmed)) {
      return
    }
    visible.add(member.user.id)
    addDescendants(member.user.id)
    addAncestors(member.user.id)
  })

  return members.filter((member) => visible.has(member.user.id))
}

export function createDefaultTeamFilters(): TeamFiltersValue {
  return {
    roles: [],
    businessFunctions: [],
    operationalSites: [],
    activity: 'all',
    coverageRange: [0, TIME_ENTRY_COVERAGE_MAX_PERCENTAGE],
  }
}

/** D-10: "Attivita' (attivo se coverage.tracked_days > 0)". */
export function isTeamMemberActive(member: TeamPulseMember): boolean {
  return member.coverage.tracked_days > 0
}

export function isTeamCoverageRangeActive(range: [number, number]): boolean {
  return range[0] > 0 || range[1] < TIME_ENTRY_COVERAGE_MAX_PERCENTAGE
}

export function countActiveTeamFilters(filters: TeamFiltersValue): number {
  return (
    filters.roles.length +
    filters.businessFunctions.length +
    filters.operationalSites.length +
    (filters.activity !== 'all' ? 1 : 0) +
    (isTeamCoverageRangeActive(filters.coverageRange) ? 1 : 0)
  )
}

export function filterTeamMembers(
  members: TeamPulseMember[],
  filters: TeamFiltersValue,
): TeamPulseMember[] {
  if (countActiveTeamFilters(filters) === 0) {
    return members
  }

  const roleSet = new Set(filters.roles)
  const functionSet = new Set(filters.businessFunctions)
  const siteSet = new Set(filters.operationalSites)
  const [minCoverage, maxCoverage] = filters.coverageRange
  const effectiveMaxCoverage =
    maxCoverage >= TIME_ENTRY_COVERAGE_MAX_PERCENTAGE ? Number.POSITIVE_INFINITY : maxCoverage

  return members.filter((member) => {
    if (roleSet.size > 0 && !member.roles.some((role) => roleSet.has(role))) {
      return false
    }
    if (
      functionSet.size > 0 &&
      !member.business_functions.some((businessFunction) => functionSet.has(businessFunction.name))
    ) {
      return false
    }
    if (siteSet.size > 0) {
      const label = member.operational_site?.label
      if (!label || !siteSet.has(label)) {
        return false
      }
    }
    if (filters.activity !== 'all') {
      const active = isTeamMemberActive(member)
      if (filters.activity === 'active' && !active) {
        return false
      }
      if (filters.activity === 'inactive' && active) {
        return false
      }
    }
    if (isTeamCoverageRangeActive(filters.coverageRange)) {
      const percentage = member.coverage.percentage
      if (percentage < minCoverage || percentage > effectiveMaxCoverage) {
        return false
      }
    }
    return true
  })
}

function uniqueSortedOptions(values: Iterable<string>): TeamOption[] {
  return Array.from(new Set(values))
    .sort((a, b) => a.localeCompare(b))
    .map((value) => ({ value, label: value }))
}

export function collectRoleOptions(members: TeamPulseMember[]): TeamOption[] {
  const values = new Set<string>()
  members.forEach((member) => member.roles.forEach((role) => values.add(role)))
  return uniqueSortedOptions(values)
}

export function collectBusinessFunctionOptions(members: TeamPulseMember[]): TeamOption[] {
  const values = new Set<string>()
  members.forEach((member) =>
    member.business_functions.forEach((businessFunction) => values.add(businessFunction.name)),
  )
  return uniqueSortedOptions(values)
}

export function collectOperationalSiteOptions(members: TeamPulseMember[]): TeamOption[] {
  const values = new Set<string>()
  members.forEach((member) => {
    if (member.operational_site?.label) {
      values.add(member.operational_site.label)
    }
  })
  return uniqueSortedOptions(values)
}
