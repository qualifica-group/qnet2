import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { contracts as en } from '@/i18n/locales/en-contracts'
import { contracts as itLocale } from '@/i18n/locales/it-contracts'

/**
 * AC-051: every string rendered by the Contracts views goes through
 * i18next, with a key present in BOTH locales.
 */

/** Recursively collects every leaf key path of a translation bundle, e.g. `actions.validateDialog.title`. */
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

describe('contracts i18n parity', () => {
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
})

/**
 * Regression guard for the class of bug found during MT-09 reconciliation:
 * a key can be present in BOTH locales (so the parity test above passes)
 * and still be broken, either because it is missing from both (i18next then
 * has nothing to resolve) or because it resolves to an OBJECT where the
 * consumer (`t(key)` expecting a string, e.g. `features/table/row-actions.tsx`
 * reading `action.label`) needs a plain string. Every key a backend catalogue
 * actually sends as a `label` is asserted here against the REAL, merged
 * `i18n` resource tree (post MT-09 registration into `en.ts`/`it.ts`), not
 * just the module-local bundle — so this also catches a merge that never
 * happened.
 *
 * Sources of truth (read, not guessed):
 * - `backend/app/Tables/Contracts/ContractColumnCatalog.php::columns()` — the
 *   18 column labels (AC-029).
 * - `backend/app/Tables/Contracts/ContractAdvancedFilterCatalog.php` — the 5
 *   advanced-filter labels.
 * - `ContractColumnCatalog::actions()` — row-action labels. Only the ones
 *   under the `contracts.actions.*` namespace are listed: `view`/`edit`/
 *   `activity` use the shared `actions.view`/`actions.edit`/`actions.activity`
 *   keys (already covered by other suites), not this module's bundle.
 */
const BACKEND_COLUMN_LABEL_KEYS = [
  'contracts.columns.code',
  'contracts.columns.title',
  'contracts.columns.registry',
  'contracts.columns.opportunity',
  'contracts.columns.commercial',
  'contracts.columns.reporter',
  'contracts.columns.supervisor',
  'contracts.columns.managers',
  'contracts.columns.contractStatus',
  'contracts.columns.quoteDate',
  'contracts.columns.acceptedAt',
  'contracts.columns.validatedAt',
  'contracts.columns.renewalDate',
  'contracts.columns.expiryDate',
  'contracts.columns.terminatedAt',
  'contracts.columns.revenueNet',
  'contracts.columns.revenueVat',
  'contracts.columns.revenueGross',
  'contracts.columns.alert',
] as const

const BACKEND_ADVANCED_FILTER_LABEL_KEYS = [
  'contracts.advancedFilters.registry',
  'contracts.advancedFilters.opportunity',
  'contracts.advancedFilters.commercial',
  'contracts.advancedFilters.supervisor',
  'contracts.advancedFilters.contractStatus',
] as const

const BACKEND_ROW_ACTION_LABEL_KEYS = [
  'contracts.actions.validate',
  'contracts.actions.schedule',
  'contracts.actions.changeStatus',
  'contracts.actions.terminate',
  'contracts.actions.reactivate',
] as const

const ALL_BACKEND_KEYS = [
  ...BACKEND_COLUMN_LABEL_KEYS,
  ...BACKEND_ADVANCED_FILTER_LABEL_KEYS,
  ...BACKEND_ROW_ACTION_LABEL_KEYS,
]

/** Reads the raw resource value at a dot-path, without going through `t()` (whose missing/object fallback behavior would mask the very bugs this test hunts). */
function resolveResource(bundle: unknown, path: string): unknown {
  return path
    .split('.')
    .reduce<unknown>(
      (node, key) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[key] : undefined),
      bundle,
    )
}

describe('contracts i18n — backend-declared labels resolve to plain strings', () => {
  it.each(ALL_BACKEND_KEYS)('"%s" is a non-empty string in both EN and IT (real merged bundle)', (key) => {
    const enBundle = i18n.getResourceBundle('en', 'translation')
    const itBundle = i18n.getResourceBundle('it', 'translation')

    const enValue = resolveResource(enBundle, key)
    const itValue = resolveResource(itBundle, key)

    expect(typeof enValue === 'string' && enValue.length > 0, `en.${key}`).toBe(true)
    expect(typeof itValue === 'string' && itValue.length > 0, `it.${key}`).toBe(true)
  })
})
