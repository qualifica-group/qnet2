import { describe, expect, it } from 'vitest'
import { invoiceEditor as enEditor } from '@/i18n/locales/en-invoice-editor'
import { invoices as enInvoices } from '@/i18n/locales/en-invoices'
import { invoiceEditor as itEditor } from '@/i18n/locales/it-invoice-editor'
import { invoices as itInvoices } from '@/i18n/locales/it-invoices'

/** Spec 0194: same leaf keys in IT and EN for both invoice modules, no empty value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe.each([
  ['invoices', itInvoices, enInvoices],
  ['invoiceEditor', itEditor, enEditor],
])('%s i18n parity (spec 0194)', (_name, itTree, enTree) => {
  it('has the same set of keys in it and en', () => {
    const itKeys = leafEntries(itTree).map(([path]) => path).sort()
    const enKeys = leafEntries(enTree).map(([path]) => path).sort()
    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty value in either language', () => {
    const empty = [...leafEntries(itTree), ...leafEntries(enTree)].filter(([, v]) => v.trim() === '')
    expect(empty).toEqual([])
  })
})
