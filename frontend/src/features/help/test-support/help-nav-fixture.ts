import type { NavigationItem } from '@/features/navigation/types'

function item(overrides: Partial<NavigationItem> & Pick<NavigationItem, 'key' | 'label'>): NavigationItem {
  return { icon: null, route: null, type: 'item', children: [], ...overrides }
}

/**
 * A small navigation tree, shaped like the real backend config (top-level
 * leaf + section + routed-parent-with-children), reused across the help
 * panel test suites so every test exercises the same fixture.
 */
export const HELP_NAV_FIXTURE: NavigationItem[] = [
  item({ key: 'dashboard', label: 'navigation.dashboard', route: '/dashboard', icon: 'layout-dashboard' }),
  item({
    key: 'administration',
    label: 'navigation.administration',
    type: 'section',
    // Real config: a `type: 'section'` node always has `icon: null` (AC-014).
    children: [
      item({ key: 'users', label: 'navigation.users', route: '/users', icon: 'users' }),
      item({ key: 'roles', label: 'navigation.roles', route: '/roles', icon: 'shield-check' }),
    ],
  }),
  item({
    key: 'opportunities-group',
    label: 'navigation.opportunitiesAndCommesse',
    icon: 'briefcase',
    children: [
      item({
        key: 'request-management',
        label: 'navigation.requestManagement',
        route: '/request-management',
        icon: 'clipboard-list',
        children: [
          item({
            key: 'field-change-requests',
            label: 'navigation.fieldChangeRequests',
            route: '/field-change-requests',
            icon: 'list-checks',
          }),
        ],
      }),
    ],
  }),
]

/** Same tree without `request-management` (and its nested child), for AC-004. */
export const HELP_NAV_FIXTURE_WITHOUT_REQUEST_MANAGEMENT: NavigationItem[] = HELP_NAV_FIXTURE.filter(
  (node) => node.key !== 'opportunities-group',
)
