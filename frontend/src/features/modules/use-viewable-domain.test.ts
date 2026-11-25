import { describe, expect, it, vi } from 'vitest'
import { resolveViewableDomain } from '@/features/modules/use-viewable-domain'

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    ['quotes', 'request-management', 'opportunities', 'work-orders', 'project-work-orders'].includes(domain) ? { domain } : undefined,
}))

function abilities(...granted: string[]) {
  return (permission: string) => granted.includes(permission)
}

describe('resolveViewableDomain', () => {
  it('opens a record in its own module when the actor may view it', () => {
    expect(resolveViewableDomain('quotes', abilities('quotes.view', 'request-management.view'))).toBe('quotes')
  })

  it('opens an Offerta in Gestione Richieste when only that module is viewable', () => {
    expect(resolveViewableDomain('quotes', abilities('request-management.view'))).toBe('request-management')
  })

  it('returns null when neither the module nor a fallback is viewable', () => {
    expect(resolveViewableDomain('quotes', abilities('opportunities.view'))).toBeNull()
  })

  it('has no fallback for a domain other than quotes', () => {
    expect(resolveViewableDomain('opportunities', abilities('request-management.view'))).toBeNull()
  })

  it('opens a Commessa link in "Commesse di Progetto" for an actor who sees only that module (2026-11-25)', () => {
    expect(resolveViewableDomain('work-orders', abilities('work-orders.view', 'project-work-orders.view'))).toBe('work-orders')
    expect(resolveViewableDomain('work-orders', abilities('project-work-orders.view'))).toBe('project-work-orders')
    expect(resolveViewableDomain('work-orders', abilities('quotes.view'))).toBeNull()
  })

  it('returns null for an unregistered domain even with its permission', () => {
    expect(resolveViewableDomain('unknown', abilities('unknown.view'))).toBeNull()
  })
})
