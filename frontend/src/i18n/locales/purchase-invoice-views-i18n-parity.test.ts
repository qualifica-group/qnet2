import { describe, expect, it } from 'vitest'
import { purchaseInvoiceViews as en } from '@/i18n/locales/en-purchase-invoice-views'
import { purchaseInvoiceViews as it_ } from '@/i18n/locales/it-purchase-invoice-views'
import { navigation as enNavigation } from '@/i18n/locales/en-navigation'
import { navigation as itNavigation } from '@/i18n/locales/it-navigation'
import { permissions as enPermissions } from '@/i18n/locales/en-permissions'
import { permissions as itPermissions } from '@/i18n/locales/it-permissions'

/** Spec 0250 AC-014: same leaf keys in IT and EN for the passive invoice views, no empty value; menu and permission labels in both languages. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('purchaseInvoiceViews i18n parity (spec 0250)', () => {
  it('has the same set of keys in it and en', () => {
    const itKeys = leafEntries(it_).map(([path]) => path).sort()
    const enKeys = leafEntries(en).map(([path]) => path).sort()
    expect(itKeys).toEqual(enKeys)
  })

  it('has no empty value in either language', () => {
    const empty = [...leafEntries(it_), ...leafEntries(en)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })

  it('labels the menu entry and the assignable module in both languages', () => {
    expect(itNavigation.purchaseInvoices).toBe('Fatture passive')
    expect(enNavigation.purchaseInvoices).toBe('Passive invoices')
    expect(itPermissions.resources['purchase-invoices']).toBe('Fatture Passive')
    expect(enPermissions.resources['purchase-invoices']).toBe('Passive invoices')
  })

  it('has the same ability verbs in both languages for the new passive invoice actions', () => {
    for (const verb of ['create', 'update', 'delete', 'export', 'viewActivity', 'downgrade'] as const) {
      expect(itPermissions.abilities[verb]).toBeTruthy()
      expect(enPermissions.abilities[verb]).toBeTruthy()
    }
  })
})
