import { z } from 'zod'
import type { TFunction } from 'i18next'
import {
  isValidCardNumber,
  isValidIban,
} from '@/features/financial-accounts/financial-account-validators'
import {
  CARD_CIRCUITS,
  CARD_TYPES,
  type CardCircuit,
  type CardType,
  type FinancialAccountType,
} from '@/features/financial-accounts/types'

/**
 * Zod schema of the financial-account form, discriminated on `type` (spec 0189).
 * Each variant validates only the fields of its type, so the parsed output (and
 * therefore the payload) never carries another type's fields. Built as a
 * factory so messages are localized; `isEdit` relaxes the card number, which on
 * edit is optional (absent = unchanged).
 */

const NAME_MAX_LENGTH = 255
const ACCOUNT_NUMBER_MAX_LENGTH = 50
const POSTAL_CODE_MAX_LENGTH = 10
const CARD_EXPIRY_PATTERN = /^(0[1-9]|1[0-2])\/\d{4}$/

/**
 * Flat set of every field the form owns: the RHF value type shared by all
 * three variants (inputs are strings, relations are nullable ids). The Zod
 * variants below each validate a subset of it.
 */
export interface FinancialAccountFormValues {
  type: FinancialAccountType
  name: string
  company_id: number | null
  iban: string
  account_number: string
  address_line: string
  postal_code: string
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
  card_type: CardType | ''
  card_circuit: CardCircuit | ''
  linked_account_id: number | null
  card_holder: string
  card_number: string
  card_expiry: string
  notes: string
}

interface SchemaOptions {
  isEdit: boolean
}

function commonFields(t: TFunction) {
  return {
    company_id: z.number().nullable(),
    notes: z.string(),
    name: z
      .string()
      .trim()
      .min(1, t('financialAccounts.form.nameRequired'))
      .max(NAME_MAX_LENGTH, t('financialAccounts.form.nameMax')),
  }
}

function addressFields(t: TFunction) {
  return {
    address_line: z.string().max(NAME_MAX_LENGTH, t('financialAccounts.form.addressLineMax')),
    postal_code: z
      .string()
      .max(POSTAL_CODE_MAX_LENGTH, t('financialAccounts.form.postalCodeMax')),
    country_id: z.number().nullable(),
    state_id: z.number().nullable(),
    province_id: z.number().nullable(),
    city_id: z.number().nullable(),
  }
}

function bankAccountSchema(t: TFunction) {
  return z.object({
    type: z.literal('bank_account'),
    ...commonFields(t),
    ...addressFields(t),
    iban: z
      .string()
      .trim()
      .min(1, t('financialAccounts.form.ibanRequired'))
      .refine(isValidIban, t('financialAccounts.form.ibanInvalid')),
    account_number: z
      .string()
      .trim()
      .min(1, t('financialAccounts.form.accountNumberRequired'))
      .max(ACCOUNT_NUMBER_MAX_LENGTH, t('financialAccounts.form.accountNumberMax')),
  })
}

function cashSchema(t: TFunction) {
  return z.object({
    type: z.literal('cash'),
    ...commonFields(t),
    ...addressFields(t),
  })
}

function cardNumberField(t: TFunction, isEdit: boolean) {
  const validated = z
    .string()
    .refine(isValidCardNumber, t('financialAccounts.form.cardNumberInvalid'))

  if (isEdit) {
    return z.union([z.literal(''), validated])
  }
  return z.string().min(1, t('financialAccounts.form.cardNumberRequired')).pipe(validated)
}

function cardSchema(t: TFunction, { isEdit }: SchemaOptions) {
  return z
    .object({
      type: z.literal('card'),
      ...commonFields(t),
      card_type: z.enum(CARD_TYPES, { error: t('financialAccounts.form.cardTypeRequired') }),
      card_circuit: z.enum(CARD_CIRCUITS, {
        error: t('financialAccounts.form.cardCircuitRequired'),
      }),
      linked_account_id: z.number().nullable(),
      card_holder: z
        .string()
        .trim()
        .min(1, t('financialAccounts.form.cardHolderRequired'))
        .max(NAME_MAX_LENGTH, t('financialAccounts.form.cardHolderMax')),
      card_number: cardNumberField(t, isEdit),
      card_expiry: z
        .string()
        .regex(CARD_EXPIRY_PATTERN, t('financialAccounts.form.cardExpiryInvalid')),
    })
    .superRefine((values, context) => {
      if (values.card_type === 'credit' && values.linked_account_id === null) {
        context.addIssue({
          code: 'custom',
          path: ['linked_account_id'],
          message: t('financialAccounts.form.linkedAccountRequired'),
        })
      }
    })
}

/** Builds the discriminated schema. Outputs contain only the chosen type's fields. */
export function buildFinancialAccountSchema(t: TFunction, options: SchemaOptions) {
  return z.discriminatedUnion('type', [
    bankAccountSchema(t),
    cardSchema(t, options),
    cashSchema(t),
  ])
}

export type FinancialAccountSchemaOutput = z.output<
  ReturnType<typeof buildFinancialAccountSchema>
>
