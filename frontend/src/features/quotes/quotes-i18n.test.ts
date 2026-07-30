import { describe, expect, it } from 'vitest'
import { en } from '@/i18n/locales/en'
import { it as itLocale } from '@/i18n/locales/it'
import { resolveIcon } from '@/features/navigation/icon-map'

// The `quotes` table is backend-driven: QuoteColumnCatalog /
// QuoteAdvancedFilterCatalog send the i18n KEY, the grid renders `t(key)`.
// A missing key here surfaces as the raw `quotes.columns.*` string in the
// header, so guard the exact key sets declared server-side.
const COLUMN_KEYS = [
  'code',
  'title',
  'opportunity',
  'quoteStatus',
  'commercial',
  'reporter',
  'supervisor',
  'revenueNet',
  'costNet',
  'marginNet',
  'createdAt',
] as const

const ADVANCED_FILTER_KEYS = [
  'opportunity',
  'quoteStatus',
  'commercial',
  'supervisor',
  'createdRange',
] as const

describe('quotes table i18n', () => {
  it('translates every backend-declared column label in both locales', () => {
    for (const key of COLUMN_KEYS) {
      expect(en.quotes.columns[key]).toBeTruthy()
      expect(itLocale.quotes.columns[key]).toBeTruthy()
    }
  })

  it('translates every backend-declared advanced filter label in both locales', () => {
    for (const key of ADVANCED_FILTER_KEYS) {
      expect(en.quotes.advancedFilters[key]).toBeTruthy()
      expect(itLocale.quotes.advancedFilters[key]).toBeTruthy()
    }
  })
})

describe('quotes navigation icon', () => {
  it('maps the `file-text` icon declared in the backend navigation config', () => {
    // resolveIcon falls back to the neutral Circle for unknown names, which is
    // what rendered as a plain dot next to "Offerte".
    expect(resolveIcon('file-text')).not.toBe(resolveIcon(null))
  })
})

// Spec 0070 AC-315: the Layout field, its form section, the detail field, the
// backend-declared row-action label (`actions.generateWord`) and every new
// document-generation message must exist in both locales.
describe('quotes document generation i18n (spec 0070)', () => {
  it('translates the shared "actions.generateWord" row-action label declared by the backend', () => {
    expect(en.actions.generateWord).toBeTruthy()
    expect(itLocale.actions.generateWord).toBeTruthy()
  })

  it('translates the Layout field, its search placeholder and its form section', () => {
    expect(en.quotes.form.layout).toBeTruthy()
    expect(itLocale.quotes.form.layout).toBeTruthy()
    expect(en.quotes.form.layoutSearch).toBeTruthy()
    expect(itLocale.quotes.form.layoutSearch).toBeTruthy()
    expect(en.quotes.form.sections.layout.title).toBeTruthy()
    expect(itLocale.quotes.form.sections.layout.title).toBeTruthy()
    expect(en.quotes.form.sections.layout.description).toBeTruthy()
    expect(itLocale.quotes.form.sections.layout.description).toBeTruthy()
  })

  it('translates the detail Layout field and the document-generation messages', () => {
    expect(en.quotes.detail.layout).toBeTruthy()
    expect(itLocale.quotes.detail.layout).toBeTruthy()
    expect(en.quotes.detail.generatingDocument).toBeTruthy()
    expect(itLocale.quotes.detail.generatingDocument).toBeTruthy()
    expect(en.quotes.detail.documentGenerated).toBeTruthy()
    expect(itLocale.quotes.detail.documentGenerated).toBeTruthy()
    expect(en.quotes.detail.documentForbidden).toBeTruthy()
    expect(itLocale.quotes.detail.documentForbidden).toBeTruthy()
    expect(en.quotes.detail.documentGenericError).toBeTruthy()
    expect(itLocale.quotes.detail.documentGenericError).toBeTruthy()
  })
})

// Directive 2026-07-30: the Note tab became "Note e pagamenti" and hosts the
// payment method picker, so its field/search/detail labels must exist in both
// locales and the tab label must no longer be the bare "Note"/"Notes".
describe('quotes payment method i18n', () => {
  it('translates the payment method field and its search placeholder', () => {
    expect(en.quotes.form.paymentMethod).toBeTruthy()
    expect(itLocale.quotes.form.paymentMethod).toBeTruthy()
    expect(en.quotes.form.paymentMethodSearch).toBeTruthy()
    expect(itLocale.quotes.form.paymentMethodSearch).toBeTruthy()
    expect(en.quotes.detail.paymentMethod).toBeTruthy()
    expect(itLocale.quotes.detail.paymentMethod).toBeTruthy()
  })

  it('renames the notes tab and section to cover payments in both locales', () => {
    expect(itLocale.quotes.form.tabs.notes).toBe('Note e pagamenti')
    expect(itLocale.quotes.form.sections.notes.title).toBe('Note e pagamenti')
    expect(en.quotes.form.tabs.notes).toBe('Notes and payments')
    expect(en.quotes.form.sections.notes.title).toBe('Notes and payments')
  })
})
