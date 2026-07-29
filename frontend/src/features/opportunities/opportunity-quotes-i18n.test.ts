import { describe, expect, it } from 'vitest'
import { en } from '@/i18n/locales/en'
import { it as itLocale } from '@/i18n/locales/it'

/** AC-080: every string of the Quotes panel is present in both locales, same keys. */
const QUOTES_PANEL_KEYS = ['title', 'countLabel', 'create', 'empty', 'emptyHint'] as const

describe('opportunity quotes panel i18n', () => {
  it('translates every panel string in both locales', () => {
    for (const key of QUOTES_PANEL_KEYS) {
      expect(en.opportunities.detail.quotes[key]).toBeTruthy()
      expect(itLocale.opportunities.detail.quotes[key]).toBeTruthy()
    }
  })
})
