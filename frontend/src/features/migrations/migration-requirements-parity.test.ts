import { describe, expect, it } from 'vitest'
import { migrationRequirements as requirementsEn } from '@/i18n/locales/en-migration-requirements'
import { migrationRequirements as requirementsIt } from '@/i18n/locales/it-migration-requirements'
import { migrations as migrationsEn } from '@/i18n/locales/en-migrations'

describe('migration requirements copy', () => {
  it('has the same sources in Italian and English', () => {
    expect(Object.keys(requirementsIt).sort()).toEqual(Object.keys(requirementsEn).sort())
  })

  it('describes only sources that have a label', () => {
    expect(Object.keys(migrationsEn.sources)).toEqual(expect.arrayContaining(Object.keys(requirementsEn)))
  })

  it('never leaves a source without words', () => {
    for (const text of [...Object.values(requirementsIt), ...Object.values(requirementsEn)]) {
      expect(text.trim()).not.toBe('')
    }
  })
})
