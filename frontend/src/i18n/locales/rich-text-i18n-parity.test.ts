import { describe, expect, it } from 'vitest'
import { richText as en } from '@/i18n/locales/en-rich-text'
import { richText as itLocale } from '@/i18n/locales/it-rich-text'

/**
 * Spec 0128, AC-026: same approach as `time-entries-i18n-parity.test.ts`.
 * `en: TranslationResources` (via `en.ts`) only enforces STRUCTURAL parity
 * (same keys) at compile time — a translated-but-empty string would still
 * typecheck.
 */

type I18nTree = { [key: string]: string | I18nTree }

/** Collects every leaf key path (e.g. `toolbar.bold`) of a nested i18n object. */
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

describe('rich text i18n parity (spec 0128 AC-026)', () => {
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
