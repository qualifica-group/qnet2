import { describe, expect, it } from 'vitest'
import { stripWorkspacePrefix, workspacePath } from '@/features/workspaces/workspace-path'
import { decideUnprefixed } from '@/features/workspaces/workspace-redirect'

describe('workspace path helpers', () => {
  it('prefixes a route and the app root', () => {
    expect(workspacePath('hr', '/invoices')).toBe('/app/hr/invoices')
    expect(workspacePath('hr', '')).toBe('/app/hr')
  })

  it.each([
    ['/app/hr/invoices', '/invoices'],
    ['/app/hr/contracts/5', '/contracts/5'],
    ['/app/hr', '/'],
    ['/invoices', '/invoices'],
    ['/application', '/application'],
  ])('strips the prefix of %s', (pathname, expected) => {
    expect(stripWorkspacePrefix(pathname)).toBe(expected)
  })
})

describe('decideUnprefixed (spec 0317 AC-014, AC-015)', () => {
  const hr = { slug: 'hr', name: 'HR', icon: null, color: null, description: null, home_route: '/absences' }
  const acc = { slug: 'acc', name: 'Acc', icon: null, color: null, description: null, home_route: '/invoices' }

  it('enters the remembered app when still available', () => {
    expect(decideUnprefixed({ workspaces: [hr, acc], full_view_available: true }, 'acc')).toEqual({
      kind: 'redirect',
      slug: 'acc',
    })
  })

  it('falls back to the first app when the remembered one is gone and the full view is closed', () => {
    expect(decideUnprefixed({ workspaces: [hr, acc], full_view_available: false }, 'gone')).toEqual({
      kind: 'redirect',
      slug: 'hr',
    })
    expect(decideUnprefixed({ workspaces: [hr], full_view_available: false }, null)).toEqual({
      kind: 'redirect',
      slug: 'hr',
    })
  })

  it('stays on the full view when allowed and nothing is remembered', () => {
    expect(decideUnprefixed({ workspaces: [hr], full_view_available: true }, null)).toEqual({ kind: 'stay' })
    expect(decideUnprefixed({ workspaces: [], full_view_available: true }, 'hr')).toEqual({ kind: 'stay' })
  })

  it('reports an empty state with no app and no full view', () => {
    expect(decideUnprefixed({ workspaces: [], full_view_available: false }, null)).toEqual({ kind: 'empty' })
  })
})
