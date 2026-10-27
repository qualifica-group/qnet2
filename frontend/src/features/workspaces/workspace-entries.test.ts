import { describe, expect, it } from 'vitest'
import { groupEntries, toggleKeys } from '@/features/workspaces/workspace-entries'
import { slugify } from '@/features/workspaces/workspace-slug'
import type { NavigationCatalogueEntry } from '@/features/workspaces/types'

const LABELS: Record<string, string> = {
  'nav.accounting': 'Contabilità',
  'nav.invoices': 'Fatture',
  'nav.ledger': 'Prima nota',
  'nav.users': 'Utenti',
}
const translate = (key: string) => LABELS[key] ?? key

function entry(key: string, label: string, path: string[]): NavigationCatalogueEntry {
  return { key, label, icon: null, route: `/${key}`, path }
}

const CATALOGUE = [
  entry('invoices', 'nav.invoices', ['nav.accounting']),
  entry('ledger', 'nav.ledger', ['nav.accounting']),
  entry('users', 'nav.users', []),
]

describe('groupEntries', () => {
  it('groups by translated path in catalogue order', () => {
    expect(groupEntries(CATALOGUE, translate).map((group) => [group.title, group.entries.length])).toEqual([
      ['Contabilità', 2],
      ['', 1],
    ])
  })

  it('filters by label or group title, ignoring case and accents, and drops empty groups', () => {
    expect(groupEntries(CATALOGUE, translate, 'PRIMA').flatMap((group) => group.entries.map((e) => e.key))).toEqual([
      'ledger',
    ])
    expect(groupEntries(CATALOGUE, translate, 'contabilita').flatMap((group) => group.entries.map((e) => e.key))).toEqual([
      'invoices',
      'ledger',
    ])
  })
})

describe('toggleKeys', () => {
  it('adds missing keys without duplicates and removes only the given ones', () => {
    expect(toggleKeys(['users', 'ledger'], ['invoices', 'ledger'], true)).toEqual(['users', 'ledger', 'invoices'])
    expect(toggleKeys(['users', 'ledger', 'invoices'], ['invoices', 'ledger'], false)).toEqual(['users'])
  })
})

describe('slugify', () => {
  it('turns a name into a URL address', () => {
    expect(slugify('Qualifica Contabilità')).toBe('qualifica-contabilita')
    expect(slugify('  HR & Paghe -- 2026 ')).toBe('hr-paghe-2026')
    expect(slugify('x'.repeat(39) + ' yz')).toBe('x'.repeat(39))
  })
})
