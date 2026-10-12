import { describe, expect, it } from 'vitest'
import { purchaseOrderForm as enForm } from '@/i18n/locales/en-purchase-order-form'
import { purchaseOrderForm as itForm } from '@/i18n/locales/it-purchase-order-form'

/**
 * Spec 0210: the ODA form bundle carries the exact same leaf keys in en and
 * it, with no empty string (the compile-time check only enforces structure).
 */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('purchaseOrderForm i18n parity (spec 0210)', () => {
  it('has the exact same set of keys in en and it', () => {
    expect(leafEntries(itForm).map(([path]) => path).sort()).toEqual(
      leafEntries(enForm).map(([path]) => path).sort(),
    )
  })

  it('has no empty string value in either language', () => {
    const empty = [...leafEntries(enForm), ...leafEntries(itForm)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
