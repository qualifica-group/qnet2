import { describe, expect, it } from 'vitest'
import { aiAssistantCosts as en } from '@/i18n/locales/en-ai-assistant-costs'
import { aiAssistantCosts as itLocale } from '@/i18n/locales/it-ai-assistant-costs'

/** Spec 0433: same key set in both locales, no blank value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('aiAssistantCosts i18n parity (spec 0433)', () => {
  it('has the exact same set of keys in en and it, recursively', () => {
    expect(leafEntries(itLocale).map(([path]) => path).sort()).toEqual(
      leafEntries(en).map(([path]) => path).sort(),
    )
  })

  it('has no blank value in either locale', () => {
    for (const [path, value] of [...leafEntries(en), ...leafEntries(itLocale)]) {
      expect(value.trim(), path).not.toBe('')
    }
  })
})
