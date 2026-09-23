import { describe, expect, it } from 'vitest'
import { dashboard as en } from '@/i18n/locales/en-dashboard'
import { dashboard as itLocale } from '@/i18n/locales/it-dashboard'

/**
 * Spec 0151 AC-013: same automated safety net as `time-entries-i18n-parity.test.ts`
 * for the new dashboard satellite — same key set in both locales, no blank value.
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

describe('dashboard i18n parity (spec 0151 AC-013)', () => {
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
