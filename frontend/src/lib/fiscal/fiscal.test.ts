import { describe, expect, it } from 'vitest'
import {
  encodeName,
  encodeSurname,
  isFemaleTaxCode,
  isValidTaxCode,
  taxCodeBirthDate,
  taxCodeNameTriple,
  taxCodeSurnameTriple,
  withoutOmocodia,
} from '@/lib/fiscal/tax-code'
import { isValidForeignFiscalCode } from '@/lib/fiscal/foreign-fiscal-code'
import { isValidVatNumber } from '@/lib/fiscal/vat-number'

// The same cases the backend twin asserts (tests/Unit/Fiscal), so the two
// implementations cannot drift apart unnoticed.

describe('tax code — format and control character', () => {
  it('accepts a code with the right control character', () => {
    expect(isValidTaxCode('RSSMRA80A01H501U')).toBe(true)
  })

  it('accepts a code typed with spaces and lowercase', () => {
    expect(isValidTaxCode(' rss mra80a01h501u ')).toBe(true)
  })

  it.each([
    ['wrong control character', 'RSSMRA80A01H501W'],
    ['too short', 'RSSMRA80A01H501'],
    ['too long', 'RSSMRA80A01H501UU'],
    ['invalid month letter', 'RSSMRA80Z01H501U'],
    ['digits where letters belong', '123MRA80A01H501U'],
    ['empty', ''],
  ])('rejects a code with %s', (_label, code) => {
    expect(isValidTaxCode(code)).toBe(false)
  })
})

describe('tax code — decoding', () => {
  it('decodes the encoded birth date', () => {
    expect(taxCodeBirthDate('RSSMRA80A01H501U')).toEqual({
      year: 80,
      month: 1,
      day: 1,
    })
  })

  it('decodes a female birth day, stripping the +40 offset', () => {
    expect(isFemaleTaxCode('BNCLRA85M45F205P')).toBe(true)
    expect(taxCodeBirthDate('BNCLRA85M45F205P')).toEqual({
      year: 85,
      month: 8,
      day: 5,
    })
  })

  it('reads an omocodia-corrected code as its plain digits', () => {
    expect(withoutOmocodia('RSSMRAU0A01H501U')).toBe('RSSMRA80A01H501U')
  })

  it('exposes the surname and name triples the code carries', () => {
    expect(taxCodeSurnameTriple('RSSMRA80A01H501U')).toBe('RSS')
    expect(taxCodeNameTriple('RSSMRA80A01H501U')).toBe('MRA')
  })
})

describe('name encoding', () => {
  it.each([
    ['Rossi', 'RSS'],
    ['Bianchi', 'BNC'],
    ['Fo', 'FOX'],
    ["D'Amico", 'DMC'],
    ['Nicolò', 'NCL'],
  ])('encodes the surname %s as %s', (surname, expected) => {
    expect(encodeSurname(surname)).toBe(expected)
  })

  it('drops the second consonant of a name with four or more', () => {
    expect(encodeName('Giuseppe')).toBe('GPP')
  })

  it('falls back to the surname rule for a name with fewer than four consonants', () => {
    expect(encodeName('Mario')).toBe('MRA')
    expect(encodeName('Ida')).toBe('DIA')
  })
})

describe('VAT number', () => {
  it('accepts a VAT number with a valid control digit', () => {
    expect(isValidVatNumber('00743110157')).toBe(true)
  })

  it('accepts a VAT number carrying the IT prefix or separators', () => {
    expect(isValidVatNumber('IT 00743110157')).toBe(true)
  })

  it.each([
    ['wrong control digit', '00743110158'],
    ['too short', '0074311015'],
    ['letters', '0074311015A'],
    ['all zeros', '00000000000'],
    ['empty', ''],
  ])('rejects a VAT number with %s', (_label, value) => {
    expect(isValidVatNumber(value)).toBe(false)
  })
})

describe('foreign code', () => {
  it.each([
    ['German VAT', 'DE123456789'],
    ['French VAT with spaces', 'fr 12 345678901'],
    ['Swiss UID', 'CHE-123.456.789'],
    ['Spanish NIF', 'ES12345678Z'],
    ['Greek VIES prefix', 'EL123456789'],
  ])('accepts a %s', (_label, value) => {
    expect(isValidForeignFiscalCode(value)).toBe(true)
  })

  it.each([
    ['no prefix', '123456789'],
    ['Italian prefix', 'IT00743110157'],
    ['Italian personal code shape', 'RSSMRA80A01H501'],
    ['too short', 'DE1'],
    ['too long', 'DE12345678901234567'],
    ['empty', ''],
  ])('rejects %s', (_label, value) => {
    expect(isValidForeignFiscalCode(value)).toBe(false)
  })
})
