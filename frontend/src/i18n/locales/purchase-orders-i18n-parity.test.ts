import { describe, expect, it } from 'vitest'
import { costTypes as enCostTypes } from '@/i18n/locales/en-cost-types'
import { costTypes as itCostTypes } from '@/i18n/locales/it-cost-types'
import { purchaseOrders as enOrders } from '@/i18n/locales/en-purchase-orders'
import { purchaseOrders as itOrders } from '@/i18n/locales/it-purchase-orders'

/**
 * Spec 0210: the cost types and purchase orders bundles carry the exact same
 * leaf keys in en and it, with no empty string (the compile-time check only
 * enforces structural parity).
 */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

const BUNDLES: [string, I18nTree, I18nTree][] = [
  ['costTypes', enCostTypes, itCostTypes],
  ['purchaseOrders', enOrders, itOrders],
]

describe.each(BUNDLES)('%s i18n parity (spec 0210)', (_name, en, itLocale) => {
  it('has the exact same set of keys in en and it', () => {
    expect(leafEntries(itLocale).map(([path]) => path).sort()).toEqual(
      leafEntries(en).map(([path]) => path).sort(),
    )
  })

  it('has no empty string value in either language', () => {
    const empty = [...leafEntries(en), ...leafEntries(itLocale)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
