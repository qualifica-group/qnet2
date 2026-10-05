import { describe, expect, it } from 'vitest'
import {
  isValidCardNumber,
  isValidIban,
  normalizeCardNumber,
  normalizeIban,
} from '@/features/financial-accounts/financial-account-validators'

describe('isValidIban', () => {
  it.each([
    'IT60X0542811101000000123456',
    'GB82 WEST 1234 5698 7654 32',
    'de89370400440532013000',
  ])('accepts %s', (iban) => {
    expect(isValidIban(iban)).toBe(true)
  })

  it.each([
    ['a wrong checksum digit', 'IT61X0542811101000000123456'],
    ['garbage', 'xyz'],
    ['empty', ''],
    ['too short', 'IT60'],
  ])('rejects %s', (_label, iban) => {
    expect(isValidIban(iban)).toBe(false)
  })

  it('normalizes to uppercase without spaces', () => {
    expect(normalizeIban(' it60 x054 2811 1010 0000 0123 456 ')).toBe('IT60X0542811101000000123456')
  })
})

describe('isValidCardNumber', () => {
  it('accepts a Luhn-valid number, spaces allowed', () => {
    expect(isValidCardNumber('4111 1111 1111 1111')).toBe(true)
    expect(isValidCardNumber('378282246310005')).toBe(true)
  })

  it('rejects a Luhn-invalid number', () => {
    expect(isValidCardNumber('4111 1111 1111 1112')).toBe(false)
  })

  it('rejects numbers outside 12-19 digits and non-digits', () => {
    expect(isValidCardNumber('42')).toBe(false)
    expect(isValidCardNumber('4111abcd11111111')).toBe(false)
    expect(isValidCardNumber('4'.repeat(20))).toBe(false)
  })

  it('strips spaces and dashes', () => {
    expect(normalizeCardNumber('4111-1111 1111-1111')).toBe('4111111111111111')
  })
})
