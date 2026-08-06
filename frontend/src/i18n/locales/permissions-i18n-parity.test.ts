import { describe, expect, it } from 'vitest'
import { permissions as en, permissionExplorer as enExplorer } from '@/i18n/locales/en-permissions'
import { permissions as itLocale, permissionExplorer as itExplorer } from '@/i18n/locales/it-permissions'

/**
 * Spec 0076, AC-020/AC-021: the permission explorer must never fall back to
 * `humanizeToken` (`features/roles/permission-labels.ts`) for a module or
 * action of the real catalogue. `en: TranslationResources` (via `en.ts`) only
 * enforces STRUCTURAL parity (same keys) at compile time — a translated-but-
 * empty string would still typecheck, and a missing catalogue entry would
 * only be caught by falling back at runtime. This test additionally asserts:
 * (a) the exact same set of leaf keys in en/it, (b) no empty string value,
 * and (c) every one of the 37 assignable modules and every action of
 * `AssignablePermissionCatalogue::names()` is present in both bundles.
 */

type I18nTree = { [key: string]: string | I18nTree }

/** Collects every leaf key path (e.g. `abilities.viewAny`) of a nested i18n object. */
function leafPaths(tree: I18nTree, prefix = ''): string[] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [path] : leafPaths(value, path)
  })
}

/** Collects every `[path, value]` leaf pair of a nested i18n object. */
function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

// The 37 assignable resource prefixes (`AssignablePermissionCatalogue::names()`,
// `backend/config/authorization.php` `definitions` + `permission_only_resources`).
const ASSIGNABLE_RESOURCES = [
  'attachments',
  'attributes',
  'business-functions',
  'campaigns',
  'commission-configurations',
  'companies',
  'company-sites',
  'contract-statuses',
  'contracts',
  'custom-fields',
  'document-layouts',
  'leads',
  'notes',
  'operational-sites',
  'opportunities',
  'payment-methods',
  'pipeline-statuses',
  'product-categories',
  'products',
  'projects',
  'quote-workflows',
  'quotes',
  'referent-types',
  'referents',
  'registries',
  'request-management',
  'reward-statuses',
  'reward-types',
  'rewarded-referents',
  'roles',
  'sectors',
  'sources',
  'tags',
  'users',
  'vat-rates',
]

// Every ability exposed by a resource policy (`BasePolicy` CRUD/export/import/
// viewActivity, plus the extras of ContractPolicy, RequestManagementPolicy and
// UserPolicy — see `backend/app/Policies`).
const CATALOGUE_ABILITIES = [
  'viewAny',
  'view',
  'create',
  'update',
  'delete',
  'export',
  'import',
  'viewActivity',
  'validate',
  'terminate',
  'schedule',
  'changeStatus',
  'reactivate',
  'viewAll',
  'viewDocuments',
  'assignOperator',
  'impersonate',
]

describe('permissions i18n parity (spec 0076 AC-020, AC-021)', () => {
  it('has the exact same set of keys in en and it, recursively', () => {
    expect(leafPaths(itLocale).sort()).toEqual(leafPaths(en).sort())
    expect(leafPaths(itExplorer).sort()).toEqual(leafPaths(enExplorer).sort())
  })

  it('has no empty string value in en', () => {
    expect(leafEntries(en).filter(([, value]) => value.trim() === '')).toEqual([])
    expect(leafEntries(enExplorer).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it('has no empty string value in it', () => {
    expect(leafEntries(itLocale).filter(([, value]) => value.trim() === '')).toEqual([])
    expect(leafEntries(itExplorer).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it.each(ASSIGNABLE_RESOURCES)('exposes a resource label for %s in en and it', (resource) => {
    expect(en.resources).toHaveProperty(resource)
    expect(itLocale.resources).toHaveProperty(resource)
  })

  it.each(CATALOGUE_ABILITIES)('exposes an ability label for %s in en and it', (ability) => {
    expect(en.abilities).toHaveProperty(ability)
    expect(itLocale.abilities).toHaveProperty(ability)
  })
})
