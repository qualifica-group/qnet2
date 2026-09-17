import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { en } from '@/i18n/locales/en'
import { it as itLocale } from '@/i18n/locales/it'
import { enumLabelOf } from '@/features/config/enum-label'

// ADR 0009 requires the bootstrap error screen to be localized. Guard the
// config.error.* key set against drift between EN and IT.
const REQUIRED_KEYS = ['title', 'description', 'retry'] as const

describe('config.error i18n parity', () => {
  it('exposes every required key in English', () => {
    for (const key of REQUIRED_KEYS) {
      expect(en.config.error[key]).toBeTruthy()
    }
  })

  it('mirrors the English keys in Italian with non-empty, distinct copy', () => {
    expect(Object.keys(itLocale.config.error).sort()).toEqual(
      Object.keys(en.config.error).sort(),
    )
    for (const key of REQUIRED_KEYS) {
      expect(itLocale.config.error[key]).toBeTruthy()
    }
    // Sanity: title/description are actually translated, not copied from EN.
    expect(itLocale.config.error.title).not.toBe(en.config.error.title)
    expect(itLocale.config.error.description).not.toBe(en.config.error.description)
  })
})

// Backend domain enums are labeled by the frontend i18n resources (not by the
// backend config). Guard the `enums.*` key set against EN/IT drift, and pin the
// `enumLabelOf` resolver behaviour (translation, then raw-value fallback).
describe('enums i18n parity', () => {
  it('mirrors every enum key and value between English and Italian', () => {
    expect(Object.keys(itLocale.enums).sort()).toEqual(Object.keys(en.enums).sort())
    for (const key of Object.keys(en.enums) as (keyof typeof en.enums)[]) {
      expect(Object.keys(itLocale.enums[key]).sort()).toEqual(
        Object.keys(en.enums[key]).sort(),
      )
      for (const value of Object.values(itLocale.enums[key])) {
        expect(value).toBeTruthy()
      }
    }
  })
})

describe('enumLabelOf', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })
  afterAll(async () => {
    await i18n.changeLanguage('it')
  })

  it('resolves a value to its Italian label', () => {
    expect(enumLabelOf('contact_type', 'phone')).toBe('Telefono')
    expect(enumLabelOf('personal_data_type', 'company')).toBe('Azienda')
  })

  it('resolves the English label after switching language', async () => {
    await i18n.changeLanguage('en')
    expect(enumLabelOf('contact_type', 'phone')).toBe('Phone')
  })

  it('falls back to the raw value when the case is not translated', () => {
    expect(enumLabelOf('contact_type', 'carrier_pigeon')).toBe('carrier_pigeon')
    expect(enumLabelOf('unknown_enum', 'whatever')).toBe('whatever')
  })
})
