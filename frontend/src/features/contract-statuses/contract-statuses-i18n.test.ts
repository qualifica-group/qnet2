import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { contractStatuses as en } from '@/i18n/locales/en-contract-statuses'
import { contractStatuses as itLocale } from '@/i18n/locales/it-contract-statuses'

/**
 * AC-051: every string rendered by the Contract Statuses views goes through
 * i18next, with a key present in BOTH locales. Mirrors `contracts-i18n.test.ts`.
 * Unlike that sibling, `it-contract-statuses.ts`/`en-contract-statuses.ts` ARE
 * already merged into `it.ts`/`en.ts` (MT-09), so this also asserts the keys
 * resolve from the live i18n instance, not just from the two bundles in
 * isolation.
 */

// The `contract-statuses` table is backend-driven: `ContractStatusColumnCatalog` /
// `ContractStatusAdvancedFilterCatalog` send the i18n KEY, the grid renders
// `t(key)`. A missing key here surfaces as the raw `contractStatuses.columns.*`/
// `contractStatuses.advancedFilters.*` string in the header/filter panel —
// this is exactly the gap a plain IT/EN parity check does NOT catch (a key
// missing in BOTH locales still "matches"). Guard the exact key sets declared
// server-side (mirrors `quotes-i18n.test.ts`), read straight off the PHP
// catalogues rather than assumed.
const BACKEND_COLUMN_KEYS = [
  'name',
  'description',
  'color',
  'sort_order',
  'is_active',
  'is_default',
  'group',
  'created_at',
] as const

const BACKEND_ADVANCED_FILTER_KEYS = [
  'name',
  'isActive',
  'isDefault',
  'sortOrderRange',
  'createdRange',
] as const

/** Recursively collects every leaf key path of a translation bundle, e.g. `form.group.label`. */
function leafKeyPaths(node: unknown, prefix = ''): string[] {
  if (typeof node === 'string') {
    return [prefix]
  }
  if (node === null || typeof node !== 'object') {
    return []
  }
  return Object.entries(node as Record<string, unknown>).flatMap(([key, value]) =>
    leafKeyPaths(value, prefix ? `${prefix}.${key}` : key),
  )
}

describe('contract statuses i18n parity', () => {
  it('declares the exact same leaf keys in IT and EN', () => {
    const itKeys = leafKeyPaths(itLocale).sort()
    const enKeys = leafKeyPaths(en).sort()
    expect(itKeys).toEqual(enKeys)
  })

  it('has a non-empty, non-placeholder string for every declared key in both locales', () => {
    for (const path of leafKeyPaths(itLocale)) {
      const itValue = path.split('.').reduce<unknown>((node, key) => (node as Record<string, unknown>)[key], itLocale)
      const enValue = path.split('.').reduce<unknown>((node, key) => (node as Record<string, unknown>)[key], en)
      expect(typeof itValue === 'string' && itValue.length > 0, `it.${path}`).toBe(true)
      expect(typeof enValue === 'string' && enValue.length > 0, `en.${path}`).toBe(true)
    }
  })

  it('resolves every declared key from the live i18n instance in both locales', () => {
    for (const path of leafKeyPaths(itLocale)) {
      const key = `contractStatuses.${path}`
      expect(i18n.t(key, { lng: 'it' }), `it:${key}`).not.toBe(key)
      expect(i18n.t(key, { lng: 'en' }), `en:${key}`).not.toBe(key)
    }
  })

  it('translates every backend-declared column label in both locales', () => {
    for (const key of BACKEND_COLUMN_KEYS) {
      expect(en.columns[key], `en:${key}`).toBeTruthy()
      expect(itLocale.columns[key], `it:${key}`).toBeTruthy()
    }
  })

  it('translates every backend-declared advanced filter label in both locales', () => {
    for (const key of BACKEND_ADVANCED_FILTER_KEYS) {
      expect(en.advancedFilters[key], `en:${key}`).toBeTruthy()
      expect(itLocale.advancedFilters[key], `it:${key}`).toBeTruthy()
    }
  })
})
