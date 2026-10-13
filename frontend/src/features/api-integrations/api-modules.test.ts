import { describe, expect, it } from 'vitest'
import { buildApiModules, groupModulesBySection, humanizeSegment } from '@/features/api-integrations/api-modules'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'
import type { NavigationItem } from '@/features/navigation/types'

function op(path: string, method: ParsedOperation['method'] = 'GET'): ParsedOperation {
  return {
    id: `${method}:${path}`,
    method,
    path,
    tag: 'Tag',
    summary: null,
    description: null,
    parameters: [],
    requestSchema: null,
    responseStatus: null,
    responseSchema: null,
  }
}

function item(key: string, route: string | null, children: NavigationItem[] = [], icon: string | null = null): NavigationItem {
  return { key, label: `navigation.${key}`, icon, route, type: route === null ? 'section' : 'item', children }
}

const navigation: NavigationItem[] = [
  item('dashboard', '/dashboard', [], 'layout-dashboard'),
  item('marketing', null, [item('leads', '/leads', [], 'megaphone'), item('campaigns', '/campaigns')]),
  item('administration', null, [item('apiIntegrations', '/admin/api-integrations', [], 'plug'), item('users', '/users')]),
]

const operations = [
  op('/users'),
  op('/users/{user}'),
  op('/leads'),
  op('/leads/{lead}', 'DELETE'),
  op('/work-order-payment-statuses'),
  op('/dashboard'),
]

describe('buildApiModules', () => {
  const modules = buildApiModules(operations, navigation)
  const byKey = Object.fromEntries(modules.map((module) => [module.key, module]))

  it('groups operations by first path segment with counts', () => {
    expect(byKey.users.operations).toHaveLength(2)
    expect(byKey.leads.operations).toHaveLength(2)
    expect(byKey.leads.basePath).toBe('/leads')
  })

  it('takes label, icon and section from the menu entry with the same route', () => {
    expect(byKey.leads).toMatchObject({
      labelKey: 'navigation.leads',
      icon: 'megaphone',
      sectionKey: 'marketing',
      sectionLabelKey: 'navigation.marketing',
    })
    expect(byKey.users.sectionKey).toBe('administration')
  })

  it('falls back to "other" with a humanized label when no entry matches', () => {
    expect(byKey['work-order-payment-statuses']).toMatchObject({
      labelKey: null,
      fallbackLabel: 'Work order payment statuses',
      icon: null,
      sectionKey: 'other',
      sectionLabelKey: null,
    })
  })

  it('puts a top-level routed entry in "other" and lists matched modules in menu order', () => {
    expect(byKey.dashboard.sectionKey).toBe('other')
    expect(modules.map((module) => module.key)).toEqual(['dashboard', 'leads', 'users', 'work-order-payment-statuses'])
  })

  it('works without any navigation', () => {
    expect(buildApiModules(operations, []).every((module) => module.sectionKey === 'other')).toBe(true)
  })
})

describe('buildApiModules with an area prefix in the menu routes', () => {
  const prefixed: NavigationItem[] = [
    item('develop', null, [
      item('apiClients', '/dev/api-clients', [], 'plug'),
      item('apiDocs', '/dev/api-docs'),
      item('usersArea', '/dev/users/legacy'),
    ]),
    item('administration', null, [item('users', '/users')]),
  ]

  it('matches the entry whose last route segment is the module', () => {
    const modules = buildApiModules([op('/api-clients'), op('/api-docs')], prefixed)
    expect(modules[0]).toMatchObject({ key: 'api-clients', labelKey: 'navigation.apiClients', icon: 'plug', sectionKey: 'develop' })
    expect(modules[1]).toMatchObject({ key: 'api-docs', sectionKey: 'develop' })
  })

  it('prefers the identical route, then the last segment, then the first segment', () => {
    const [users] = buildApiModules([op('/users')], prefixed)
    expect(users.sectionKey).toBe('administration')
    const [dev] = buildApiModules([op('/dev')], prefixed)
    expect(dev).toMatchObject({ labelKey: 'navigation.apiClients' })
    expect(buildApiModules([op('/legacy')], prefixed)[0]).toMatchObject({ labelKey: 'navigation.usersArea' })
  })
})

describe('groupModulesBySection', () => {
  it('keeps menu sections in order with "other" last', () => {
    const sections = groupModulesBySection(buildApiModules(operations, navigation))
    expect(sections.map((section) => section.key)).toEqual(['marketing', 'administration', 'other'])
    expect(sections[2].modules.map((module) => module.key)).toEqual(['dashboard', 'work-order-payment-statuses'])
  })
})

describe('humanizeSegment', () => {
  it('replaces dashes and capitalizes', () => {
    expect(humanizeSegment('task-templates')).toBe('Task templates')
  })
})
