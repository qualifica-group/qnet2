import { describe, expect, it } from 'vitest'
import { documentChecklistCategories as en } from '@/i18n/locales/en-document-checklist-categories'
import { documentChecklistCategories as itLocale } from '@/i18n/locales/it-document-checklist-categories'

/** Spec 0410: same leaf keys in en and it, no empty value (mirrors document-checklists-i18n-parity). */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('documentChecklistCategories i18n parity (spec 0410)', () => {
  it('has the exact same set of keys in en and it', () => {
    const keys = (tree: I18nTree) => leafEntries(tree).map(([path]) => path).sort()
    expect(keys(itLocale)).toEqual(keys(en))
  })

  it('has no empty string value', () => {
    const empty = [...leafEntries(en), ...leafEntries(itLocale)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
