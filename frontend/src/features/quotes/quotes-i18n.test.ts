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
