import { describe, expect, it, vi } from 'vitest'
import { moduleActionHref, withModuleActionHref } from '@/features/modules/module-action-href'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) => {
    if (domain === 'projects') {
      return { domain, basePath: '/projects' }
    }
    if (domain === 'registries') {
      return { domain, basePath: '/registries', generateRoutes: false }
    }
    return undefined
  },
}))

function action(key: string): TableActionDefinition {
  return { key, label: key, icon: 'eye', type: 'action', confirm: false }
}

const ROW: TableRow = { id: 5, actions: [], contract_id: 9 }

describe('moduleActionHref', () => {
  it('leads view to the record page and duplicate to its duplicate form', () => {
    const href = moduleActionHref('projects')

    expect(href?.(action('view'), ROW)).toBe('/projects/5')
    expect(href?.(action('duplicate'), ROW)).toBe('/projects/5/duplicate')
    expect(href?.(action('activity'), ROW)).toBeUndefined()
  })

  it('gives no duplicate page to a module with bespoke routes', () => {
    const href = moduleActionHref('registries')

    expect(href?.(action('view'), ROW)).toBe('/registries/5')
    expect(href?.(action('duplicate'), ROW)).toBeUndefined()
  })

  it('resolves nothing for an unregistered domain', () => {
    expect(moduleActionHref('purchase-requests')).toBeUndefined()
  })
})

describe('withModuleActionHref', () => {
  const contractHref = (definition: TableActionDefinition, row: TableRow) =>
    definition.key === 'contract' ? `/contracts/${String(row.contract_id)}` : undefined

  it('asks the domain resolver first, then the module pages', () => {
    const href = withModuleActionHref('projects', contractHref)

    expect(href?.(action('contract'), ROW)).toBe('/contracts/9')
    expect(href?.(action('view'), ROW)).toBe('/projects/5')
  })

  it('falls back to the domain resolver alone for an unregistered domain', () => {
    expect(withModuleActionHref('purchase-requests', contractHref)).toBe(contractHref)
    expect(withModuleActionHref('purchase-requests')).toBeUndefined()
  })
})
