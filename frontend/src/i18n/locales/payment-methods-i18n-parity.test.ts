import { describe, expect, it } from 'vitest'
import { paymentMethods as en } from '@/i18n/locales/en-payment-methods'
import { paymentMethods as itLocale } from '@/i18n/locales/it-payment-methods'

/**
 * Spec 0068, AC-114: automates what would otherwise be a manual inspection.
 * `en: TranslationResources` (via `en.ts`) only enforces STRUCTURAL parity
 * (same keys) at compile time — a translated-but-empty string would still
 * typecheck. This test additionally walks both modules and asserts: (a) the
 * exact same set of leaf keys, recursively, and (b) no empty string value in
 * either language.
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

describe('paymentMethods i18n parity (spec 0068 AC-114)', () => {
  it('has the exact same set of keys in en and it, recursively', () => {
    const enKeys = leafPaths(en).sort()
    const itKeys = leafPaths(itLocale).sort()

    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty string value in en', () => {
    const empty = leafEntries(en).filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })

  it('has no empty string value in it', () => {
    const empty = leafEntries(itLocale).filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
