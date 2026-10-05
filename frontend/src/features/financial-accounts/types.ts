/**
 * Financial accounts types. Generic table types live in `features/table/types.ts`;
 * this file holds the resource and its write payloads. Source of truth: spec 0189
 * frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

export const FINANCIAL_ACCOUNT_TYPES = ['bank_account', 'card', 'cash'] as const
export type FinancialAccountType = (typeof FINANCIAL_ACCOUNT_TYPES)[number]

export const CARD_TYPES = ['credit', 'prepaid'] as const
export type CardType = (typeof CARD_TYPES)[number]

export const CARD_CIRCUITS = ['visa', 'mastercard', 'amex'] as const
export type CardCircuit = (typeof CARD_CIRCUITS)[number]

/** `{id, name}` projection of a related record. */
export interface NamedRef {
  id: number
  name: string
}

/** Single financial account returned by GET/POST/PATCH /financial-accounts (envelope `data`). */
export interface FinancialAccountDetail {
  id: number
  type: FinancialAccountType
  /** Banca (bank_account, card) or Nome (cash). */
  name: string
  company: NamedRef | null
  iban: string | null
  account_number: string | null
  address_line: string | null
  postal_code: string | null
  country: NamedRef | null
  state: NamedRef | null
  province: NamedRef | null
  city: NamedRef | null
  card_type: CardType | null
  card_circuit: CardCircuit | null
  linked_account: { id: number; name: string; iban: string | null } | null
  card_holder: string | null
  /** Always masked (`**** 1234`); the full number only comes from the reveal endpoint. */
  card_number_masked: string | null
  card_expiry: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

/** Detail carrying the actor's authorization metadata (`GET /financial-accounts/{id}`). */
export interface FinancialAccountDetailWithPermissions extends FinancialAccountDetail {
  permissions: ResourcePermissions
}

interface GeoPayload {
  address_line: string | null
  postal_code: string | null
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
}

export interface BankAccountPayload extends GeoPayload {
  type: 'bank_account'
  name: string
  iban: string
  account_number: string
  company_id: number | null
  notes: string | null
}

export interface CardPayload {
  type: 'card'
  card_type: CardType
  name: string
  card_circuit: CardCircuit
  linked_account_id: number | null
  card_holder: string
  /** Omitted on update when the user did not type a new number. */
  card_number?: string
  card_expiry: string
  company_id: number | null
  notes: string | null
}

export interface CashPayload extends GeoPayload {
  type: 'cash'
  name: string
  company_id: number | null
  notes: string | null
}

/** Write payload (store and update share the shape); only the chosen type's keys are present. */
export type FinancialAccountPayload = BankAccountPayload | CardPayload | CashPayload

/** Discriminated form mode shared by the form hook/meta-resolver and the component. */
export type FinancialAccountFormMode =
  | { type: 'create' }
  | { type: 'edit'; financialAccount: FinancialAccountDetailWithPermissions }
