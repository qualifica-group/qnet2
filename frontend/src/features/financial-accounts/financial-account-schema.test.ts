import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildFinancialAccountSchema } from '@/features/financial-accounts/financial-account-schema'
import {
  EMPTY_FORM_VALUES,
  buildFinancialAccountPayload,
} from '@/features/financial-accounts/financial-account-form-payload'

/** Identity translator: assertions target message keys, not localized copy. */
const t = ((key: string) => key) as unknown as TFunction

const VALID_IBAN = 'IT60 X054 2811 1010 0000 0123 456'
const VALID_CARD = '4111 1111 1111 1111'

const bankAccount = {
  ...EMPTY_FORM_VALUES,
  type: 'bank_account' as const,
  name: 'Intesa',
  iban: VALID_IBAN,
  account_number: '123456',
}

const card = {
  ...EMPTY_FORM_VALUES,
  type: 'card' as const,
  name: 'Intesa',
  card_type: 'credit' as const,
  card_circuit: 'visa' as const,
  linked_account_id: 3,
  card_holder: 'Mario Rossi',
  card_number: VALID_CARD,
  card_expiry: '12/2030',
}

const cash = { ...EMPTY_FORM_VALUES, type: 'cash' as const, name: 'Cassa sede' }

function failingPaths(schema: ReturnType<typeof buildFinancialAccountSchema>, values: object) {
  const result = schema.safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('financial account schema (AC-021)', () => {
  const create = buildFinancialAccountSchema(t, { isEdit: false })
  const edit = buildFinancialAccountSchema(t, { isEdit: true })

  it('accepts a valid payload of each of the three types', () => {
    expect(create.safeParse(bankAccount).success).toBe(true)
    expect(create.safeParse(card).success).toBe(true)
    expect(create.safeParse(cash).success).toBe(true)
  })

  it('rejects an invalid IBAN', () => {
    expect(failingPaths(create, { ...bankAccount, iban: 'IT61X0542811101000000123456' })).toContain('iban')
    expect(failingPaths(create, { ...bankAccount, iban: 'xyz' })).toContain('iban')
  })

  it('requires the linked account for a credit card only', () => {
    expect(failingPaths(create, { ...card, linked_account_id: null })).toContain('linked_account_id')
    expect(
      create.safeParse({ ...card, card_type: 'prepaid', linked_account_id: null }).success,
    ).toBe(true)
  })

  it('rejects a card number failing Luhn and a malformed expiry', () => {
    expect(failingPaths(create, { ...card, card_number: '4111 1111 1111 1112' })).toContain('card_number')
    expect(failingPaths(create, { ...card, card_expiry: '2030-12' })).toContain('card_expiry')
    expect(failingPaths(create, { ...card, card_expiry: '13/2030' })).toContain('card_expiry')
  })

  it('requires the card number on create but not on edit', () => {
    expect(failingPaths(create, { ...card, card_number: '' })).toContain('card_number')
    expect(edit.safeParse({ ...card, card_number: '' }).success).toBe(true)
    expect(failingPaths(edit, { ...card, card_number: '1234' })).toContain('card_number')
  })

  it('sends only the chosen type fields in the payload', () => {
    const cashParsed = create.parse({ ...cash, iban: VALID_IBAN, card_number: VALID_CARD })
    const cashPayload = buildFinancialAccountPayload(cashParsed)
    expect(cashPayload).not.toHaveProperty('iban')
    expect(cashPayload).not.toHaveProperty('card_number')
    expect(cashPayload).not.toHaveProperty('card_type')

    const bankPayload = buildFinancialAccountPayload(create.parse(bankAccount))
    expect(bankPayload).toMatchObject({ type: 'bank_account', iban: 'IT60X0542811101000000123456' })
    expect(bankPayload).not.toHaveProperty('card_holder')
    expect(bankPayload).not.toHaveProperty('card_number')
  })

  it('normalizes the card number and omits it when left empty on edit', () => {
    expect(buildFinancialAccountPayload(create.parse(card))).toMatchObject({
      card_number: '4111111111111111',
    })
    const unchanged = buildFinancialAccountPayload(edit.parse({ ...card, card_number: '' }))
    expect(unchanged).not.toHaveProperty('card_number')
    expect(unchanged).not.toHaveProperty('cvv')
  })

  it('turns empty optional text into null', () => {
    expect(buildFinancialAccountPayload(create.parse(cash))).toMatchObject({
      address_line: null,
      postal_code: null,
      notes: null,
      company_id: null,
    })
  })
})
