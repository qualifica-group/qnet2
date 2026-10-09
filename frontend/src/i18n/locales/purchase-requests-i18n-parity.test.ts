import { describe, expect, it } from 'vitest'
import {
  purchaseRequestLines as enLines,
  purchaseRequests as en,
} from '@/i18n/locales/en-purchase-requests'
import {
  purchaseRequestLines as itLines,
  purchaseRequests as itLocale,
} from '@/i18n/locales/it-purchase-requests'

/**
 * Spec 0208: same set of leaf keys in IT and EN (recursively) and no empty
 * value, for the RDA list/form strings and the line management columns.
 */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe.each([
  ['purchaseRequests', en, itLocale],
  ['purchaseRequestLines', enLines, itLines],
])('%s i18n parity (spec 0208)', (_name, enTree, itTree) => {
  it('has the exact same set of keys in en and it, recursively', () => {
    const enKeys = leafEntries(enTree).map(([path]) => path).sort()
    const itKeys = leafEntries(itTree).map(([path]) => path).sort()

    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty string value in either language', () => {
    const empty = [...leafEntries(enTree), ...leafEntries(itTree)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
