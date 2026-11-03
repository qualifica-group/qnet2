import { describe, expect, it } from 'vitest'
import { aiAssistant as en } from '@/i18n/locales/en-ai-assistant'
import { aiAssistant as itLocale } from '@/i18n/locales/it-ai-assistant'
import { AI_MENTION_MODULES } from '@/features/ai-assistant/mention-modules'

/** Spec 0347: same key set in both locales, no blank value, every mention module labelled. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('aiAssistant i18n parity (spec 0347)', () => {
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

  it('labels all 18 mention modules used by the data layer', () => {
    expect(AI_MENTION_MODULES).toHaveLength(18)
    for (const { labelKey } of AI_MENTION_MODULES) {
      const key = labelKey.replace('aiAssistant.', '')
      for (const locale of [en, itLocale]) {
        expect(leafEntries(locale).some(([path]) => path === key), key).toBe(true)
      }
    }
  })
})
