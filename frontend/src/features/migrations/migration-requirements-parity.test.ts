import { describe, expect, it } from 'vitest'
import { migrationRequirements as requirementsEn } from '@/i18n/locales/en-migration-requirements'
import { migrationRequirements as requirementsIt } from '@/i18n/locales/it-migration-requirements'
import { migrations as migrationsEn } from '@/i18n/locales/en-migrations'
import { migrations as migrationsIt } from '@/i18n/locales/it-migrations'

describe('migration requirements copy', () => {
  it('has the same sources in Italian and English', () => {
    expect(Object.keys(requirementsIt).sort()).toEqual(Object.keys(requirementsEn).sort())
  })

  it('labels the same sources in Italian and English', () => {
    expect(Object.keys(migrationsIt.sources).sort()).toEqual(Object.keys(migrationsEn.sources).sort())
  })

  it('labels and describes the quote acceptance dates source in both languages', () => {
    expect(migrationsIt.sources['quote-accepted-dates']).toBe('Offerte — data accettazione')
    expect(migrationsEn.sources['quote-accepted-dates']).toBe('Quotes — acceptance date')
    expect(requirementsIt['quote-accepted-dates']).toContain('stato vinto')
    expect(requirementsEn['quote-accepted-dates']).toContain('won state')
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
