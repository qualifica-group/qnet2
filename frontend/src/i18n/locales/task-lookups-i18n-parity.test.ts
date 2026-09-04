import { describe, expect, it } from 'vitest'
import {
  taskCategories as enCategories,
  taskImportances as enImportances,
  taskPriorities as enPriorities,
  taskStatuses as enStatuses,
  taskTypes as enTypes,
} from '@/i18n/locales/en-task-lookups'
import {
  taskCategories as itCategories,
  taskImportances as itImportances,
  taskPriorities as itPriorities,
  taskStatuses as itStatuses,
  taskTypes as itTypes,
} from '@/i18n/locales/it-task-lookups'

/**
 * Spec 0101, AC-090, for the five Task configurators. `en: TranslationResources`
 * (via `en.ts`) already enforces STRUCTURAL parity at compile time — a
 * translated-but-empty string would still typecheck, and a key nobody
 * translated at all would only surface as a raw `taskTypes.form.name` in the
 * UI. This test asserts three things a type check cannot:
 *
 *  (a) identical leaf-key sets in en/it, recursively;
 *  (b) no empty string value in either language;
 *  (c) every key the module actually RESOLVES is present. That third list is
 *      the union of three sources, transcribed here because a frontend unit
 *      test can read none of them directly: the feature code under
 *      `features/task-*`, the `columns.*` label keys each backend
 *      `ColumnCatalog` sends as `TableColumn.label`, and the keys the GENERIC
 *      module hosts resolve through template literals
 *      (`use-module-opener.tsx`, `module-form-page.tsx`) — the last group
 *      being the one a grep of the feature folder silently misses.
 */

type I18nTree = { [key: string]: string | I18nTree }

/** Collects every leaf key path (e.g. `form.sections.identity.title`). */
function leafPaths(tree: I18nTree, prefix = ''): string[] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [path] : leafPaths(value, path)
  })
}

/** Collects every `[path, value]` leaf pair. */
function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

/** Resolves a dotted path against a bundle, or `undefined` when absent. */
function at(tree: I18nTree, path: string): string | I18nTree | undefined {
  return path
    .split('.')
    .reduce<string | I18nTree | undefined>(
      (node, key) => (node && typeof node === 'object' ? node[key] : undefined),
      tree,
    )
}

/**
 * Keys the four non-status lookups share (D-4: identical shape). The
 * per-entity `form.newTask*` key is asserted separately, since its NAME
 * differs per module.
 */
const SHARED_KEYS = [
  'columns.color',
  'columns.created_at',
  'columns.description',
  'columns.icon',
  'columns.is_active',
  'columns.name',
  'columns.sort_order',
  'detail.color',
  'detail.created_at',
  'detail.description',
  'detail.icon',
  'detail.isActive',
  'detail.loadError',
  'detail.sort_order',
  'detail.updated_at',
  'form.cancel',
  'form.color',
  'form.colorInvalid',
  'form.colorRequired',
  'form.created',
  'form.deleted',
  'form.deleteError',
  'form.deleteForbidden',
  'form.deleteInUse',
  'form.description',
  'form.descriptionMax',
  'form.genericError',
  'form.icon',
  'form.iconInvalid',
  'form.isActive',
  'form.name',
  'form.nameMax',
  'form.nameRequired',
  'form.save',
  'form.saving',
  'form.sections.identity.description',
  'form.sections.identity.title',
  'form.updated',
  'reorder.dragHandleLabel',
  'reorder.forbidden',
  'reorder.genericError',
  'reorder.inactiveBadge',
  'reorder.loadError',
  'reorder.openButton',
  'reorder.saved',
  'reorder.subtitle',
  'reorder.title',
]

/** What `task_statuses` carries on top of the shared set (D-5/D-6). */
const STATUS_ONLY_KEYS = [
  'columns.completion_percentage',
  'detail.completionPercentage',
  'form.completionPercentage',
  'form.completionPercentageInvalid',
  'form.completionPercentageRange',
  'form.completionPercentageRequired',
  'form.hints.systemStatusFields',
  'form.newTaskStatus',
]

const LOOKUPS = [
  ['taskTypes', enTypes, itTypes, 'form.newTaskType'],
  ['taskCategories', enCategories, itCategories, 'form.newTaskCategory'],
  ['taskPriorities', enPriorities, itPriorities, 'form.newTaskPriority'],
  ['taskImportances', enImportances, itImportances, 'form.newTaskImportance'],
] as const

const ALL_BUNDLES = [
  ['taskStatuses', enStatuses, itStatuses],
  ...LOOKUPS.map(([name, en, it]) => [name, en, it] as const),
] as const

describe.each(ALL_BUNDLES)('%s i18n parity (spec 0101 AC-090)', (_name, en, itLocale) => {
  it('has the exact same set of keys in en and it, recursively', () => {
    expect(leafPaths(itLocale).sort()).toEqual(leafPaths(en).sort())
  })

  it('has no empty string value in en', () => {
    expect(leafEntries(en).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it('has no empty string value in it', () => {
    expect(leafEntries(itLocale).filter(([, value]) => value.trim() === '')).toEqual([])
  })
})

describe.each(LOOKUPS)('%s resolves every key the module uses', (_name, en, itLocale, newKey) => {
  it('covers the shared lookup key set in both languages', () => {
    expect(SHARED_KEYS.filter((key) => typeof at(en, key) !== 'string')).toEqual([])
    expect(SHARED_KEYS.filter((key) => typeof at(itLocale, key) !== 'string')).toEqual([])
  })

  it('covers its own entity-specific create label', () => {
    expect(typeof at(en, newKey)).toBe('string')
    expect(typeof at(itLocale, newKey)).toBe('string')
  })
})

describe('taskStatuses resolves every key the module uses', () => {
  it('covers the shared lookup key set plus its own extras, in both languages', () => {
    const required = [...SHARED_KEYS, ...STATUS_ONLY_KEYS]
    expect(required.filter((key) => typeof at(enStatuses, key) !== 'string')).toEqual([])
    expect(required.filter((key) => typeof at(itStatuses, key) !== 'string')).toEqual([])
  })
})
