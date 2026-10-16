import { describe, expect, it } from 'vitest'
import { managementControl as en } from '@/i18n/locales/en-management-control'
import { managementControl as it_ } from '@/i18n/locales/it-management-control'

/** Spec 0268: same leaf keys in IT and EN for the management control module, no empty value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('managementControl i18n parity (spec 0268)', () => {
  it('has the same set of keys in it and en', () => {
    expect(leafEntries(it_).map(([path]) => path).sort()).toEqual(leafEntries(en).map(([path]) => path).sort())
  })

  it('has no empty value in either language', () => {
    const empty = [...leafEntries(it_), ...leafEntries(en)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })
})
