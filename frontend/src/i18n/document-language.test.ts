// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest'
import i18n, { applyLocale, defaultLocale } from '@/i18n'

describe('document language', () => {
  afterEach(async () => {
    await i18n.changeLanguage(defaultLocale)
  })

  it('declares the default locale after init', () => {
    expect(document.documentElement.lang).toBe(defaultLocale)
  })

  it('follows the active locale when it changes', async () => {
    applyLocale('en')
    await i18n.loadLanguages('en')

    expect(document.documentElement.lang).toBe('en')
  })

  it('ignores a locale the app does not ship', () => {
    applyLocale('fr')

    expect(document.documentElement.lang).toBe(defaultLocale)
  })
})
