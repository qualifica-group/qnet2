import { describe, expect, it } from 'vitest'
import { apiIntegrations as en } from '@/i18n/locales/en-api-integrations'
import { apiIntegrations as itLocale } from '@/i18n/locales/it-api-integrations'
import { apiClients as apiClientsEn } from '@/i18n/locales/en-api-clients'
import { apiClients as apiClientsIt } from '@/i18n/locales/it-api-clients'

/** Spec 0209: same key set in both locales, no blank value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

describe('apiIntegrations i18n parity (spec 0209)', () => {
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

  it('keeps the api-clients table labels aligned across locales', () => {
    expect(leafEntries(apiClientsIt).map(([path]) => path).sort()).toEqual(
      leafEntries(apiClientsEn).map(([path]) => path).sort(),
    )
  })
})
