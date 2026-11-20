import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildPersonalDataSchema(t)

describe('personal-data schema (per-type requirements)', () => {
  it('requires first and last name for an individual', () => {
    expect(schema.safeParse({ type: 'individual' }).success).toBe(false)
    expect(
      schema.safeParse({
        type: 'individual',
        first_name: 'Ada',
        last_name: 'Lovelace',
      }).success,
    ).toBe(true)
  })

  it('requires a company name for a company', () => {
    expect(schema.safeParse({ type: 'company' }).success).toBe(false)
    expect(
      schema.safeParse({ type: 'company', company_name: 'Engines Ltd' }).success,
    ).toBe(true)
  })

  it('rejects a future birth date', () => {
    const tomorrow = new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)
    expect(
      schema.safeParse({
        type: 'individual',
        first_name: 'Ada',
        last_name: 'Lovelace',
        birth_date: tomorrow,
      }).success,
    ).toBe(false)
  })
})

describe('personal-data schema (fiscal identifiers)', () => {
  const individual = {
    type: 'individual' as const,
    first_name: 'Mario',
    last_name: 'Rossi',
  }

  it('accepts a tax code consistent with the whole card', () => {
    expect(
      schema.safeParse({
        ...individual,
        birth_date: '1980-01-01',
        gender: 'male',
        tax_code: 'RSSMRA80A01H501U',
      }).success,
    ).toBe(true)
  })

  it('rejects a tax code whose control character is wrong', () => {
    const result = schema.safeParse({ ...individual, tax_code: 'RSSMRA80A01H501W' })

    expect(result.success).toBe(false)
    expect(result.error?.issues[0].message).toBe('personalData.form.taxCodeInvalid')
  })

  it.each([
    [
      'last name',
      { ...individual, last_name: 'Bianchi' },
      'personalData.form.taxCodeLastNameMismatch',
    ],
    [
      'first name',
      { ...individual, first_name: 'Luigi' },
      'personalData.form.taxCodeFirstNameMismatch',
    ],
    [
      'birth date',
      { ...individual, birth_date: '1980-02-01' },
      'personalData.form.taxCodeBirthDateMismatch',
    ],
    [
      'gender',
      { ...individual, gender: 'female' as const },
      'personalData.form.taxCodeGenderMismatch',
    ],
  ])('rejects a tax code that does not match the %s', (_label, values, message) => {
    const result = schema.safeParse({ ...values, tax_code: 'RSSMRA80A01H501U' })

    expect(result.success).toBe(false)
    expect(result.error?.issues[0].message).toBe(message)
  })

  it('requires the 11-digit numeric code on a company card', () => {
    const company = { type: 'company' as const, company_name: 'Acme SpA' }

    expect(schema.safeParse({ ...company, tax_code: '00743110157' }).success).toBe(true)

    const result = schema.safeParse({ ...company, tax_code: 'RSSMRA80A01H501U' })

    expect(result.success).toBe(false)
    expect(result.error?.issues[0].message).toBe(
      'personalData.form.companyTaxCodeInvalid',
    )
  })

  it('accepts foreign codes written with their country prefix', () => {
    const company = { type: 'company' as const, company_name: 'Acme GmbH' }

    expect(
      schema.safeParse({ ...individual, birth_date: '1980-01-01', tax_code: 'DE12345678901' })
        .success,
    ).toBe(true)
    expect(schema.safeParse({ ...company, tax_code: 'FR12345678901' }).success).toBe(true)
    expect(schema.safeParse({ ...company, vat_number: 'DE 123456789' }).success).toBe(true)
  })

  it('rejects an invalid VAT number and accepts a blank one', () => {
    expect(schema.safeParse({ ...individual, vat_number: '' }).success).toBe(true)
    expect(schema.safeParse({ ...individual, vat_number: '00743110157' }).success).toBe(
      true,
    )

    const result = schema.safeParse({ ...individual, vat_number: '00743110158' })

    expect(result.success).toBe(false)
    expect(result.error?.issues[0].message).toBe('personalData.form.vatNumberInvalid')
  })
})
