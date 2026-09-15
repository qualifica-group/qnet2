import { describe, expect, it } from 'vitest'
import { productLines as en } from '@/i18n/locales/en-product-lines'
import { productLines as itLocale } from '@/i18n/locales/it-product-lines'

/**
 * Spec 0129 AC-026: the "all categories" checkbox/read-only strings added to
 * the shared `productLines` domain must exist, translated, in both languages.
 * Same pattern as the other `*-i18n-parity.test.ts` suites: structural parity
 * (same leaf keys) plus a non-empty check, since TypeScript alone only checks
 * shape, not that a value was actually translated.
 */

type I18nTree = { [key: string]: string | I18nTree }

function leafPaths(tree: I18nTree, prefix = ''): string[] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [path] : leafPaths(value, path)
  })
}

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('productLines i18n parity (spec 0129 AC-026)', () => {
  it('has the exact same set of keys in en and it', () => {
    expect(leafPaths(itLocale).sort()).toEqual(leafPaths(en).sort())
  })

  it('has no empty string value in en', () => {
    expect(leafEntries(en).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it('has no empty string value in it', () => {
    expect(leafEntries(itLocale).filter(([, value]) => value.trim() === '')).toEqual([])
  })
})
