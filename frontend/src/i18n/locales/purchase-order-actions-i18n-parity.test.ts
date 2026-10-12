import { describe, expect, it } from 'vitest'
import { purchaseOrderActions as en } from '@/i18n/locales/en-purchase-order-actions'
import { purchaseOrderActions as itLocale } from '@/i18n/locales/it-purchase-order-actions'

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('purchase order actions i18n parity (spec 0210 MT-11)', () => {
  it('has the exact same set of keys in en and it, recursively', () => {
    expect(leafEntries(itLocale).map(([path]) => path).sort()).toEqual(leafEntries(en).map(([path]) => path).sort())
  })

  it('has no blank value in either locale', () => {
    for (const [path, value] of [...leafEntries(en), ...leafEntries(itLocale)]) {
      expect(value.trim(), path).not.toBe('')
    }
  })
})
