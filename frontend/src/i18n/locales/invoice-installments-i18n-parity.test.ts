import { describe, expect, it } from 'vitest'
import { invoiceInstallments as en } from '@/i18n/locales/en-invoice-installments'
import { invoiceInstallments as it_ } from '@/i18n/locales/it-invoice-installments'

/** Spec 0197: same leaf keys in IT and EN for the due dates module, no empty value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('invoiceInstallments i18n parity (spec 0197)', () => {
  it('has the same set of keys in it and en', () => {
    const itKeys = leafEntries(it_).map(([path]) => path).sort()
    const enKeys = leafEntries(en).map(([path]) => path).sort()
    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty value in either language', () => {
    const empty = [...leafEntries(it_), ...leafEntries(en)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
