import { describe, expect, it } from 'vitest'
import {
  formatContactValue,
  formatIdentityField,
  formatPersonName,
  formatPhone,
  formatPlainText,
  formatSdiCode,
  formatTaxCode,
  formatVatNumber,
} from './input-format'

// Twin of `backend/tests/Unit/Support/InputFormatTest.php` — same cases, so a
// divergence between the two implementations fails on both sides.

describe('formatPhone', () => {
  it.each(['333 12 34 567', '333-1234567', '(333) 1234567', ' 333.123.4567 ', '333/1234567'])(
    'collapses %s onto one national number',
    (typed) => {
      expect(formatPhone(typed)).toBe('3331234567')
    },
  )

  it.each(['+39 333 1234567', '+39-333-1234567', '0039 333 1234567', '(0039) 3331234567'])(
    'keeps the international prefix of %s as a leading plus',
    (typed) => {
      expect(formatPhone(typed)).toBe('+393331234567')
    },
  )

  it('never invents a country prefix that was not typed', () => {
    expect(formatPhone('02 1234567')).toBe('021234567')
  })

  it('drops a plus that is not in first position', () => {
    expect(formatPhone('333+444')).toBe('333444')
  })
})

describe('formatPersonName', () => {
  it.each([
    ['  mario   rossi ', 'Mario Rossi'],
    ['MARIO ROSSI', 'Mario Rossi'],
    ['MaRiO rOsSi', 'Mario Rossi'],
    ['de luca', 'De Luca'],
    ['di maria rossi', 'Di Maria Rossi'],
    ['anna-maria', 'Anna-Maria'],
    ['josé', 'José'],
  ])('title-cases %s', (typed, expected) => {
    expect(formatPersonName(typed)).toBe(expected)
  })

  it.each([
    ["d'angelo", "D'Angelo"],
    ["DELL'ACQUA", "Dell'Acqua"],
    ['o’connor', 'O’Connor'],
  ])('uppercases the letter after the apostrophe of %s', (typed, expected) => {
    expect(formatPersonName(typed)).toBe(expected)
  })
})

describe('fiscal identifiers and plain text', () => {
  it('leaves a company name case alone and only collapses its spacing', () => {
    expect(formatPlainText('  ACME   S.R.L. ')).toBe('ACME S.R.L.')
  })

  it('uppercases a tax code and strips its separators', () => {
    expect(formatTaxCode(' rss mra80a01h501u ')).toBe('RSSMRA80A01H501U')
  })

  it('strips the optional IT prefix from a VAT number', () => {
    expect(formatVatNumber('IT 12345678903')).toBe('12345678903')
  })

  it('uppercases an SDI code and strips its separators', () => {
    expect(formatSdiCode(' abc-1234 ')).toBe('ABC1234')
  })
})

describe('formatIdentityField', () => {
  it.each([
    ['first_name', '  ada ', 'Ada'],
    ['last_name', 'LOVELACE', 'Lovelace'],
    ['company_name', '  ACME   SRL ', 'ACME SRL'],
    ['tax_code', ' lvldaa80a01h501v ', 'LVLDAA80A01H501V'],
    ['vat_number', 'IT12345678903', '12345678903'],
    ['sdi_code', 'abc1234', 'ABC1234'],
    ['birth_date', '1980-01-01', '1980-01-01'],
  ])('formats %s', (field, typed, expected) => {
    expect(formatIdentityField(field, typed)).toBe(expected)
  })

  it('reads a company tax code as the eleven-digit code', () => {
    expect(formatIdentityField('tax_code', 'IT12345678903', true)).toBe('12345678903')
  })
})

describe('formatContactValue', () => {
  it.each([
    ['phone', '333 12 34 567', '3331234567'],
    ['fax', '02 / 1234567', '021234567'],
    ['email', '  Mario.Rossi@Example.COM ', 'mario.rossi@example.com'],
    ['pec', ' MARIO@PEC.IT', 'mario@pec.it'],
    // A URL path IS case-sensitive: only the surrounding blanks go.
    ['website', ' https://Example.com/Path ', 'https://Example.com/Path'],
  ])('formats a %s value', (type, typed, expected) => {
    expect(formatContactValue(type, typed)).toBe(expected)
  })
})
