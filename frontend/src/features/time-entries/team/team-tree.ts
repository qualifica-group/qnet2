/**
 * Pure tree/search/filter helpers for the team view (spec 0122 D-10/AC-038).
 * No React: `use-team-filters.ts` and `time-entries-team-pulse.tsx` compose
 * these over the `TeamPulse.items` fetched by `use-time-entries-team.ts`.
 * The tree is built client-side on `manager_id` (a single manager per member,
 * unlike q-net's multi-manager `managers[]`) — a member without a manager IN
 * THE LIST is a root, same rule `is_full_list` or not.
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

/** Builds the manager/subordinate tree, each level sorted by name. */
export function buildTeamTree(members: TeamPulseMember[]): TeamTreeNode[] {
  const byId = new Map(members.map((member) => [member.user.id, member]))
  const childrenByManager = new Map<number, TeamPulseMember[]>()
  const roots: TeamPulseMember[] = []

  members.forEach((member) => {
    const managerId = member.manager_id
    if (managerId != null && byId.has(managerId)) {
      const siblings = childrenByManager.get(managerId) ?? []
      siblings.push(member)
      childrenByManager.set(managerId, siblings)
    } else {
      roots.push(member)
    }
  })

  function toNode(member: TeamPulseMember): TeamTreeNode {
    const children = (childrenByManager.get(member.user.id) ?? []).slice().sort(byMemberName)
    return { key: String(member.user.id), member, children: children.map(toNode) }
  }

  return roots.slice().sort(byMemberName).map(toNode)
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
 * (so a matched descendant stays reachable in the tree) and every descendant
 * (so a matched manager still shows their whole branch) — AC-038.
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
    if (member.manager_id == null) {
      return
    }
    const siblings = childrenByManager.get(member.manager_id) ?? []
    siblings.push(member)
    childrenByManager.set(member.manager_id, siblings)
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

  members.forEach((member) => {
    if (!memberMatchesSearch(member, trimmed)) {
      return
    }
    visible.add(member.user.id)
    addDescendants(member.user.id)

    let managerId = member.manager_id
    while (managerId != null && !visible.has(managerId)) {
      visible.add(managerId)
      managerId = byId.get(managerId)?.manager_id ?? null
    }
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
