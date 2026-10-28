import { describe, expect, it } from 'vitest'
import { HELP_GUIDE_KEYS } from '@/features/help/help-guide-keys'
import itGuide from '@/features/help/content/it/workspaces'
import enGuide from '@/features/help/content/en/workspaces'
import itGeneral from '@/features/help/content/it/general'
import enGeneral from '@/features/help/content/en/general'
import { workspaces as en } from '@/i18n/locales/en-workspaces'
import { workspaces as itLocale } from '@/i18n/locales/it-workspaces'

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('workspaces manual (spec 0317 AC-020)', () => {
  // REQUIREMENT CHANGED (spec 0322): the "work-order-workflows" guide makes 99.
  it('registers the "workspaces" guide: 99 keys in total', () => {
    expect(HELP_GUIDE_KEYS).toContain('workspaces')
    expect(HELP_GUIDE_KEYS).toHaveLength(99)
  })

  it('has the guide in IT and EN with the same section ids', () => {
    expect(itGuide.key).toBe('workspaces')
    expect(enGuide.key).toBe('workspaces')
    expect(itGuide.sections.map((section) => section.id)).toEqual(enGuide.sections.map((section) => section.id))
  })

  it('has the launcher section in the general guide, in both languages', () => {
    expect(itGeneral.sections.some((section) => section.id === 'apps-launcher')).toBe(true)
    expect(enGeneral.sections.some((section) => section.id === 'apps-launcher')).toBe(true)
  })
})

describe('workspaces i18n parity (spec 0317)', () => {
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
