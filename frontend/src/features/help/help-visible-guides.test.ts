import { describe, expect, it } from 'vitest'
import type { NavigationItem } from '@/features/navigation/types'
import { flattenVisibleHelpGuides, groupConsecutiveHelpGuides } from '@/features/help/help-visible-guides'

const translate = (key: string) => key

function item(overrides: Partial<NavigationItem> & Pick<NavigationItem, 'key' | 'label'>): NavigationItem {
  return { icon: null, route: null, type: 'item', children: [], ...overrides }
}

/** A small tree mirroring the real shapes: top-level leaf, section, route-less group, nested leaf-with-children. */
const TREE: NavigationItem[] = [
  item({ key: 'dashboard', label: 'nav.dashboard', route: '/dashboard' }),
  item({
    key: 'administration',
    label: 'nav.administration',
    type: 'section',
    children: [
      item({ key: 'users', label: 'nav.users', route: '/users' }),
      item({ key: 'roles', label: 'nav.roles', route: '/roles' }),
      item({ key: 'not-a-guide-key', label: 'nav.other', route: '/other' }),
    ],
  }),
  item({
    key: 'marketing-leads',
    label: 'nav.marketingLeads',
    children: [
      item({
        key: 'leads',
        label: 'nav.leads',
        route: '/leads',
        children: [item({ key: 'imports', label: 'nav.imports', route: '/imports' })],
      }),
      item({ key: 'pipeline-statuses', label: 'nav.pipelineStatuses', route: '/pipeline-statuses' }),
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
})
