import { describe, expect, it } from 'vitest'
import { help as en } from '@/i18n/locales/en-help'
import { help as itLocale } from '@/i18n/locales/it-help'

/**
 * Spec 0143: panel interface labels (not guide content, which has its own
 * parity test in `features/help/content/help-content-parity.test.ts`). Same
 * approach as `rich-text-i18n-parity.test.ts`.
 */
describe('help panel i18n parity (spec 0143)', () => {
  it('has the exact same set of keys in en and it', () => {
    expect(Object.keys(itLocale).sort()).toEqual(Object.keys(en).sort())
  })

  it('has no empty string value in en', () => {
    expect(Object.values(en).filter((value) => value.trim() === '')).toEqual([])
  })

  it('has no empty string value in it', () => {
    expect(Object.values(itLocale).filter((value) => value.trim() === '')).toEqual([])
  })
})
