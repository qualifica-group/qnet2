import { describe, expect, it } from 'vitest'
import { tasks as en } from '@/i18n/locales/en-tasks'
import { tasks as itLocale } from '@/i18n/locales/it-tasks'
import { navigation as enNavigation } from '@/i18n/locales/en-navigation'
import { navigation as itNavigation } from '@/i18n/locales/it-navigation'

/**
 * Spec 0101, AC-090: automates what would otherwise be a manual inspection.
 * `en: TranslationResources` (via `en.ts`) only enforces STRUCTURAL parity
 * (same keys) at compile time — a translated-but-empty string would still
 * typecheck. This test additionally walks both bundles and asserts: (a) the
 * exact same set of leaf keys, recursively, (b) no empty string value in
 * either language, and (c) the navigation labels the backend's
 * `config/navigation/*.php` entries point at.
 */

type I18nTree = { [key: string]: string | I18nTree }

/** Collects every leaf key path (e.g. `form.sections.identity.title`) of a nested i18n object. */
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

/**
 * The `navigation.*` leaves `config/navigation/opportunities.php` and
 * `config/navigation/configuration.php` reference for the six new modules. A
 * missing one renders the raw key in the sidebar, which no type check catches.
 */
const NAVIGATION_KEYS = [
  'tasks',
  'taskStatuses',
  'taskTypes',
  'taskCategories',
  'taskPriorities',
  'taskImportances',
] as const

describe('tasks i18n parity (spec 0101 AC-090)', () => {
  it('has the exact same set of keys in en and it, recursively', () => {
    expect(leafPaths(itLocale).sort()).toEqual(leafPaths(en).sort())
  })

  it('has no empty string value in en', () => {
    expect(leafEntries(en).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it('has no empty string value in it', () => {
    expect(leafEntries(itLocale).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it.each(NAVIGATION_KEYS)('exposes the navigation label %s in en and it', (key) => {
    expect(enNavigation[key]).toBeTruthy()
    expect(itNavigation[key]).toBeTruthy()
  })
})

/**
 * The grid resolves a column header through the i18n key the BACKEND sends as
 * `TableColumn.label` (`column-def-builder.ts:98`), so a column declared by
 * `TaskColumnCatalog` with no entry here renders its raw key. Pinned as a
 * literal list because a frontend unit test cannot read the PHP catalogue:
 * it is the contract, transcribed, and it fails loudly if the bundle drifts.
 */
const BACKEND_COLUMN_IDS = [
  'title',
  'registry',
  'task_type',
  'task_status',
  'task_priority',
  'task_importance',
  'task_category',
  'start_date',
  'end_date',
  'completion_date',
  'requester',
  'creator',
  'assignees',
  'watchers',
  'completion_percentage',
  'estimated_minutes',
  'is_blocked',
  'opportunity',
  'work_order',
  'has_subtasks',
  'is_subtask',
] as const

describe('tasks column labels cover TaskColumnCatalog', () => {
  it.each(BACKEND_COLUMN_IDS)('labels the %s column in en and it', (id) => {
    expect(en.columns).toHaveProperty(id)
    expect(itLocale.columns).toHaveProperty(id)
  })

  it('declares no column label the backend does not send', () => {
    expect(Object.keys(en.columns).sort()).toEqual([...BACKEND_COLUMN_IDS].sort())
  })
})
