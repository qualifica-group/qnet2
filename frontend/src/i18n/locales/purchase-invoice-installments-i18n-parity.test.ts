import { describe, expect, it } from 'vitest'
import { purchaseInvoiceInstallments as en } from '@/i18n/locales/en-purchase-invoice-installments'
import { purchaseInvoiceInstallments as it_ } from '@/i18n/locales/it-purchase-invoice-installments'
import { enums as enEnums } from '@/i18n/locales/en-enums'
import { enums as itEnums } from '@/i18n/locales/it-enums'

/** Spec 0211 AC-018: same leaf keys in IT and EN for the payable installments module, no empty value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('purchaseInvoiceInstallments i18n parity (spec 0211)', () => {
  it('has the same set of keys in it and en', () => {
    const itKeys = leafEntries(it_).map(([path]) => path).sort()
    const enKeys = leafEntries(en).map(([path]) => path).sort()
    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty value in either language', () => {
    const empty = [...leafEntries(it_), ...leafEntries(en)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })

  it('translates the new enums in both languages with the same values', () => {
    for (const key of ['purchase_installment_status', 'purchase_invoice_type'] as const) {
      expect(Object.keys(itEnums[key]).sort()).toEqual(Object.keys(enEnums[key]).sort())
    }
    expect(Object.keys(enEnums.purchase_installment_status).sort()).toEqual(['paid', 'unpaid'])
  })
})
