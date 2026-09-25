import { describe, expect, it } from 'vitest'
import {
  buildTeamTree,
  collectBusinessFunctionOptions,
  collectExpandableKeys,
  collectOperationalSiteOptions,
  collectRoleOptions,
  countActiveTeamFilters,
  createDefaultTeamFilters,
  filterMembersKeepingAncestorsAndDescendants,
  filterTeamMembers,
  isTeamCoverageRangeActive,
  isTeamMemberActive,
  memberMatchesSearch,
} from '@/features/time-entries/team/team-tree'
import type { TeamPulseMember } from '@/features/time-entries/types'

/**
 * Spec 0122 AC-038: three-level tree, search keeps ancestors/descendants,
 * coverage 50..100 filter. Spec 0166 D-7/AC-013: multi-manager branches,
 * path keys, cycle guard (`manager_id` -> `manager_ids`, declared per §ROUTING).
 */

function member(overrides: Partial<TeamPulseMember> & { id: number; name: string }): TeamPulseMember {
  const { id, name, ...rest } = overrides
  return {
    user: { id, name, email: `${name.toLowerCase().replace(/\s+/g, '.')}@example.test`, avatar_url: null },
    manager_ids: [],
    job_description: null,
    roles: [],
    business_functions: [],
    operational_site: null,
    coverage: { percentage: 0, working_days: 5, tracked_days: 0 },
    primary_cluster: null,
    ...rest,
  }
}

// Three levels: CEO(1) -> Manager(2) -> IC(3), plus an unrelated root(4).
const ceo = member({ id: 1, name: 'Anna Ceo', roles: ['Admin'] })
const manager = member({ id: 2, name: 'Bruno Manager', manager_ids: [1], roles: ['Manager'] })
const contributor = member({
  id: 3,
  name: 'Carla Contributor',
  manager_ids: [2],
  roles: ['Developer'],
  operational_site: { id: 1, label: 'Milano' },
  business_functions: [{ id: 10, name: 'Backend', is_manager: false }],
  coverage: { percentage: 75, working_days: 5, tracked_days: 4 },
})
const outsider = member({ id: 4, name: 'Dario Outsider', roles: ['Sales'], coverage: { percentage: 20, working_days: 5, tracked_days: 1 } })

const THREE_LEVEL_TEAM = [ceo, manager, contributor, outsider]

describe('buildTeamTree', () => {
  it('nests three levels on manager_ids, roots first, each level sorted by name', () => {
    const tree = buildTeamTree(THREE_LEVEL_TEAM)

    expect(tree.map((node) => node.member.user.name)).toEqual(['Anna Ceo', 'Dario Outsider'])
    const ceoNode = tree[0]
    expect(ceoNode.children).toHaveLength(1)
    expect(ceoNode.children[0].member.user.name).toBe('Bruno Manager')
    expect(ceoNode.children[0].children[0].member.user.name).toBe('Carla Contributor')
  })

  it('treats a member whose manager is not in the list as a root', () => {
    const orphan = member({ id: 5, name: 'Elio Orphan', manager_ids: [999] })
    const tree = buildTeamTree([ceo, orphan])

    expect(tree.map((node) => node.member.user.name)).toEqual(['Anna Ceo', 'Elio Orphan'])
  })

  it('AC-013: a member under two managers appears under both, with distinct path keys', () => {
    const managerA = member({ id: 10, name: 'Aldo A', roles: ['Manager'] })
    const managerB = member({ id: 11, name: 'Bice B', roles: ['Manager'] })
    const shared = member({ id: 12, name: 'Carlo Shared', manager_ids: [10, 11] })

    const tree = buildTeamTree([managerA, managerB, shared])

    expect(tree.map((node) => node.member.user.name)).toEqual(['Aldo A', 'Bice B'])
    const underA = tree[0].children[0]
    const underB = tree[1].children[0]
    expect(underA.member.user.id).toBe(12)
    expect(underB.member.user.id).toBe(12)
    expect(underA.key).toBe('10/12')
    expect(underB.key).toBe('11/12')
    expect(underA.key).not.toBe(underB.key)
  })

  it('AC-013: a member is root only when NONE of its managers is in the list', () => {
    const managerA = member({ id: 10, name: 'Aldo A' })
    // manager_ids references 11 (not present) and, on purpose, does not include 10.
    const rootedElsewhere = member({ id: 12, name: 'Carlo Root', manager_ids: [11] })

    const tree = buildTeamTree([managerA, rootedElsewhere])

    expect(tree.map((node) => node.member.user.name)).toEqual(['Aldo A', 'Carlo Root'])
  })

  it('AC-013: a data cycle does not loop the traversal and a rooted member still renders', () => {
    const cycleA = member({ id: 20, name: 'Aldo Cycle', manager_ids: [21] })
    const cycleB = member({ id: 21, name: 'Bice Cycle', manager_ids: [20] })
    const rooted = member({ id: 22, name: 'Carlo Rooted' })

    const tree = buildTeamTree([cycleA, cycleB, rooted])

    expect(tree.map((node) => node.member.user.name)).toEqual(['Carlo Rooted'])
  })
})

describe('collectExpandableKeys', () => {
  it('lists only nodes with children', () => {
    const tree = buildTeamTree(THREE_LEVEL_TEAM)

    expect(collectExpandableKeys(tree)).toEqual([String(ceo.user.id), `${ceo.user.id}/${manager.user.id}`])
  })
})

describe('memberMatchesSearch', () => {
  it('matches name, role, site and job description case-insensitively', () => {
    expect(memberMatchesSearch(contributor, 'carla')).toBe(true)
    expect(memberMatchesSearch(contributor, 'DEVELOPER')).toBe(true)
    expect(memberMatchesSearch(contributor, 'milano')).toBe(true)
    expect(memberMatchesSearch(outsider, 'carla')).toBe(false)
  })

  it('an empty search matches everything', () => {
    expect(memberMatchesSearch(outsider, '  ')).toBe(true)
  })
})

describe('filterMembersKeepingAncestorsAndDescendants', () => {
  it('keeps the matched member, its ancestors and its descendants', () => {
    const result = filterMembersKeepingAncestorsAndDescendants(THREE_LEVEL_TEAM, 'bruno')

    expect(result.map((m) => m.user.name).sort()).toEqual(['Anna Ceo', 'Bruno Manager', 'Carla Contributor'])
  })

  it('a leaf match keeps only its ancestor chain, not unrelated siblings', () => {
    const result = filterMembersKeepingAncestorsAndDescendants(THREE_LEVEL_TEAM, 'carla')

    expect(result.map((m) => m.user.name).sort()).toEqual(['Anna Ceo', 'Bruno Manager', 'Carla Contributor'])
  })

  it('no match returns an empty list', () => {
    expect(filterMembersKeepingAncestorsAndDescendants(THREE_LEVEL_TEAM, 'zzz')).toEqual([])
  })

  it('AC-013: a search match under two managers keeps both branches, and builds both nodes', () => {
    const managerA = member({ id: 10, name: 'Aldo A' })
    const managerB = member({ id: 11, name: 'Bice B' })
    const shared = member({ id: 12, name: 'Carlo Shared', manager_ids: [10, 11] })

    const filtered = filterMembersKeepingAncestorsAndDescendants([managerA, managerB, shared], 'carlo')
    expect(filtered.map((m) => m.user.name).sort()).toEqual(['Aldo A', 'Bice B', 'Carlo Shared'])

    const tree = buildTeamTree(filtered)
    expect(tree.map((node) => node.member.user.name)).toEqual(['Aldo A', 'Bice B'])
    expect(tree[0].children[0].member.user.name).toBe('Carlo Shared')
    expect(tree[1].children[0].member.user.name).toBe('Carlo Shared')
  })

  it('AC-013: an ancestor cycle does not loop the walk', () => {
    const cycleA = member({ id: 20, name: 'Aldo Cycle', manager_ids: [21] })
    const cycleB = member({ id: 21, name: 'Bice Cycle', manager_ids: [20] })

    const filtered = filterMembersKeepingAncestorsAndDescendants([cycleA, cycleB], 'aldo')

    expect(filtered.map((m) => m.user.name).sort()).toEqual(['Aldo Cycle', 'Bice Cycle'])
  })
})

describe('isTeamMemberActive', () => {
  it('is active when tracked_days > 0 (D-10)', () => {
    expect(isTeamMemberActive(contributor)).toBe(true)
    expect(isTeamMemberActive(ceo)).toBe(false)
  })
})

describe('isTeamCoverageRangeActive / countActiveTeamFilters', () => {
  it('the default range [0, 101] is inactive and counts as zero filters', () => {
    const defaults = createDefaultTeamFilters()
    expect(isTeamCoverageRangeActive(defaults.coverageRange)).toBe(false)
    expect(countActiveTeamFilters(defaults)).toBe(0)
  })

  it('a narrowed range is active and counts as one filter', () => {
    const filters = { ...createDefaultTeamFilters(), coverageRange: [50, 100] as [number, number] }
    expect(isTeamCoverageRangeActive(filters.coverageRange)).toBe(true)
    expect(countActiveTeamFilters(filters)).toBe(1)
  })
})

describe('filterTeamMembers', () => {
  it('coverage range 50..100 excludes members outside it', () => {
    const filters = { ...createDefaultTeamFilters(), coverageRange: [50, 100] as [number, number] }

    const result = filterTeamMembers(THREE_LEVEL_TEAM, filters)

    expect(result.map((m) => m.user.name)).toEqual(['Carla Contributor'])
  })

  it('filters by role', () => {
    const filters = { ...createDefaultTeamFilters(), roles: ['Manager'] }

    expect(filterTeamMembers(THREE_LEVEL_TEAM, filters).map((m) => m.user.name)).toEqual(['Bruno Manager'])
  })

  it('filters by activity', () => {
    const filters = { ...createDefaultTeamFilters(), activity: 'active' as const }

    expect(filterTeamMembers(THREE_LEVEL_TEAM, filters).map((m) => m.user.name)).toEqual([
      'Carla Contributor',
      'Dario Outsider',
    ])
  })
})

describe('option collectors', () => {
  it('return distinct, alphabetically sorted values', () => {
    expect(collectRoleOptions(THREE_LEVEL_TEAM).map((o) => o.value)).toEqual([
      'Admin',
      'Developer',
      'Manager',
      'Sales',
    ])
    expect(collectBusinessFunctionOptions(THREE_LEVEL_TEAM).map((o) => o.value)).toEqual(['Backend'])
    expect(collectOperationalSiteOptions(THREE_LEVEL_TEAM).map((o) => o.value)).toEqual(['Milano'])
  })
})
