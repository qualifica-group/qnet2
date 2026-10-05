import type {
  FinancialAccountFormValues,
  FinancialAccountSchemaOutput,
} from '@/features/financial-accounts/financial-account-schema'
import {
  normalizeCardNumber,
  normalizeIban,
} from '@/features/financial-accounts/financial-account-validators'
import type {
  FinancialAccountDetail,
  FinancialAccountPayload,
} from '@/features/financial-accounts/types'

/** Empty text inputs travel as null (nullable columns), never as ''. */
function textOrNull(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

/**
 * Builds the write payload from the validated form output. The output of the
 * discriminated schema only holds the chosen type's fields, so the payload
 * never carries another type's keys. `card_number` is sent only when the user
 * typed one: on edit an empty input means "unchanged" and the key is omitted.
 */
export function buildFinancialAccountPayload(
  values: FinancialAccountSchemaOutput,
): FinancialAccountPayload {
  switch (values.type) {
    case 'bank_account':
      return {
        type: 'bank_account',
        name: values.name,
        iban: normalizeIban(values.iban),
        account_number: values.account_number,
        company_id: values.company_id,
        address_line: textOrNull(values.address_line),
        postal_code: textOrNull(values.postal_code),
        country_id: values.country_id,
        state_id: values.state_id,
        province_id: values.province_id,
        city_id: values.city_id,
        notes: textOrNull(values.notes),
      }
    case 'cash':
      return {
        type: 'cash',
        name: values.name,
        company_id: values.company_id,
        address_line: textOrNull(values.address_line),
        postal_code: textOrNull(values.postal_code),
        country_id: values.country_id,
        state_id: values.state_id,
        province_id: values.province_id,
        city_id: values.city_id,
        notes: textOrNull(values.notes),
      }
    case 'card': {
      const cardNumber = values.card_number.trim()
      return {
        type: 'card',
        card_type: values.card_type,
        name: values.name,
        card_circuit: values.card_circuit,
        linked_account_id: values.linked_account_id,
        card_holder: values.card_holder,
        ...(cardNumber === '' ? {} : { card_number: normalizeCardNumber(cardNumber) }),
        card_expiry: values.card_expiry,
        company_id: values.company_id,
        notes: textOrNull(values.notes),
      }
    }
  }
}

/** Empty form for a new account of the given type. */
export const EMPTY_FORM_VALUES: FinancialAccountFormValues = {
  type: 'bank_account',
  name: '',
  company_id: null,
  iban: '',
  account_number: '',
  address_line: '',
  postal_code: '',
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
  card_type: '',
  card_circuit: '',
  linked_account_id: null,
  card_holder: '',
  card_number: '',
  card_expiry: '',
  notes: '',
}

/**
 * Hydrates the form from a persisted account. The card number is never
 * hydrated (only its mask is known): it starts empty and is replaced only if
 * the user types a new one.
 */
export function toFormValues(account: FinancialAccountDetail): FinancialAccountFormValues {
  return {
    type: account.type,
    name: account.name,
    company_id: account.company?.id ?? null,
    iban: account.iban ?? '',
    account_number: account.account_number ?? '',
    address_line: account.address_line ?? '',
    postal_code: account.postal_code ?? '',
    country_id: account.country?.id ?? null,
    state_id: account.state?.id ?? null,
    province_id: account.province?.id ?? null,
    city_id: account.city?.id ?? null,
    card_type: account.card_type ?? '',
    card_circuit: account.card_circuit ?? '',
    linked_account_id: account.linked_account?.id ?? null,
    card_holder: account.card_holder ?? '',
    card_number: '',
    card_expiry: account.card_expiry ?? '',
    notes: account.notes ?? '',
  }
}
