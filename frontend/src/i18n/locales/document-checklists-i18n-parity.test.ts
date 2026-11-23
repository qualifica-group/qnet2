import { describe, expect, it } from 'vitest'
import { documentChecklists as en } from '@/i18n/locales/en-document-checklists'
import { documentChecklists as itLocale } from '@/i18n/locales/it-document-checklists'

/** Spec 0403: same leaf keys in en and it, no empty value (mirrors document-bundles-i18n-parity). */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('documentChecklists i18n parity (spec 0403)', () => {
  it('has the exact same set of keys in en and it', () => {
    const keys = (tree: I18nTree) => leafEntries(tree).map(([path]) => path).sort()
    expect(keys(itLocale)).toEqual(keys(en))
  })

  it('has no empty string value', () => {
    const empty = [...leafEntries(en), ...leafEntries(itLocale)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
