import { describe, expect, it } from 'vitest'
import { proformaRequests as en } from '@/i18n/locales/en-proforma-requests'
import { proformaRequests as itLocale } from '@/i18n/locales/it-proforma-requests'

/**
 * Spec 0193, AC-008: same set of leaf keys in IT and EN (recursively) and no
 * empty value. Structural parity alone would let a translated-but-empty
 * string typecheck.
 */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('proformaRequests i18n parity (spec 0193 AC-008)', () => {
  it('has the exact same set of keys in en and it, recursively', () => {
    const enKeys = leafEntries(en).map(([path]) => path).sort()
    const itKeys = leafEntries(itLocale).map(([path]) => path).sort()

    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty string value in either language', () => {
    const empty = [...leafEntries(en), ...leafEntries(itLocale)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
