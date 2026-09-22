import { describe, expect, it } from 'vitest'
import type { NavigationItem } from '@/features/navigation/types'
import { flattenVisibleHelpGuides, groupConsecutiveHelpGuides } from '@/features/help/help-visible-guides'

const translate = (key: string) => key

function item(overrides: Partial<NavigationItem> & Pick<NavigationItem, 'key' | 'label'>): NavigationItem {
  return { icon: null, route: null, type: 'item', children: [], ...overrides }
}

/** A small tree mirroring the real shapes: top-level leaf, section, route-less group, nested leaf-with-children. */
const TREE: NavigationItem[] = [
  item({ key: 'dashboard', label: 'nav.dashboard', route: '/dashboard', icon: 'layout-dashboard' }),
  item({
    key: 'administration',
    label: 'nav.administration',
    type: 'section',
    // Sections never carry their own icon (backend config), unlike route-less groups.
    children: [
      item({ key: 'users', label: 'nav.users', route: '/users', icon: 'users' }),
      item({ key: 'roles', label: 'nav.roles', route: '/roles', icon: 'shield-check' }),
      item({ key: 'not-a-guide-key', label: 'nav.other', route: '/other' }),
    ],
  }),
  item({
    key: 'marketing-leads',
    label: 'nav.marketingLeads',
    icon: 'megaphone',
    children: [
      item({
        key: 'leads',
        label: 'nav.leads',
        route: '/leads',
        icon: 'handshake',
        children: [item({ key: 'imports', label: 'nav.imports', route: '/imports', icon: 'file-up' })],
      }),
      item({ key: 'pipeline-statuses', label: 'nav.pipelineStatuses', route: '/pipeline-statuses', icon: 'waypoints' }),
    ],
  }),
]

describe('flattenVisibleHelpGuides (AC-004)', () => {
  it('keeps only routed nodes whose key is an authored guide, in tree order', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    expect(guides.map((guide) => guide.key)).toEqual([
      'dashboard',
      'users',
      'roles',
      'leads',
      'imports',
      'pipeline-statuses',
    ])
  })

  it('excludes a menu node absent from the current tree entirely (AC-004)', () => {
    const withoutLeads = flattenVisibleHelpGuides(
      TREE.filter((node) => node.key !== 'marketing-leads'),
      translate,
    )
    expect(withoutLeads.some((guide) => guide.key === 'leads')).toBe(false)
  })

  it('gives a top-level leaf no group', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    expect(guides.find((guide) => guide.key === 'dashboard')?.groupLabel).toBeNull()
  })

  it('groups section children under the translated section label', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    expect(guides.find((guide) => guide.key === 'users')?.groupLabel).toBe('nav.administration')
    expect(guides.find((guide) => guide.key === 'roles')?.groupLabel).toBe('nav.administration')
  })

  it('groups a route-less collapsible parent children under its own label, including nested leaves', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    expect(guides.find((guide) => guide.key === 'leads')?.groupLabel).toBe('nav.marketingLeads')
    // `imports` nests under `leads` (which HAS a route): it inherits the group
    // from its ancestor GROUP, not from `leads` itself (a module, not a group).
    expect(guides.find((guide) => guide.key === 'imports')?.groupLabel).toBe('nav.marketingLeads')
  })

  it('carries each guide\'s own menu icon (AC-014)', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    expect(guides.find((guide) => guide.key === 'users')?.icon).toBe('users')
    expect(guides.find((guide) => guide.key === 'roles')?.icon).toBe('shield-check')
  })

  it('carries the group icon of a route-less collapsible parent, and null for a section (AC-014)', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    expect(guides.find((guide) => guide.key === 'users')?.groupIcon).toBeNull()
    expect(guides.find((guide) => guide.key === 'leads')?.groupIcon).toBe('megaphone')
    expect(guides.find((guide) => guide.key === 'imports')?.groupIcon).toBe('megaphone')
  })
})

describe('groupConsecutiveHelpGuides', () => {
  it('buckets same-group guides together, preserving order', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    const groups = groupConsecutiveHelpGuides(guides)

    expect(groups.map((group) => group.label)).toEqual([
      null,
      'nav.administration',
      'nav.marketingLeads',
    ])
    expect(groups[1]?.guides.map((guide) => guide.key)).toEqual(['users', 'roles'])
    expect(groups[2]?.guides.map((guide) => guide.key)).toEqual(['leads', 'imports', 'pipeline-statuses'])
  })

  it('carries the group icon on each bucket (AC-014)', () => {
    const guides = flattenVisibleHelpGuides(TREE, translate)
    const groups = groupConsecutiveHelpGuides(guides)

    expect(groups.map((group) => group.icon)).toEqual([null, null, 'megaphone'])
  })
})
