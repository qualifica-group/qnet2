/** Treasury movements types (spec 0360 data_contract, frozen). */

export const TREASURY_MOVEMENT_TYPES = ['income', 'expense', 'transfer', 'adjustment'] as const
export type TreasuryMovementType = (typeof TREASURY_MOVEMENT_TYPES)[number]

export const TREASURY_DIRECTIONS = ['in', 'out'] as const
export type TreasuryDirection = (typeof TREASURY_DIRECTIONS)[number]

export const SUB_ACCOUNT_KINDS = ['cost', 'revenue', 'financial'] as const
export type SubAccountKind = (typeof SUB_ACCOUNT_KINDS)[number]

/** Domain key of the generic table and `attachable_type` alias of the attachments. */
export const TREASURY_MOVEMENTS_DOMAIN = 'treasury-movements'
export const TREASURY_MOVEMENT_ATTACHABLE_TYPE = 'treasury_movement'

export interface TreasuryAccountRef {
  id: number
  name: string
  type: string
  company: { id: number; name: string } | null
}

export interface TreasurySubAccountRef {
  kind: SubAccountKind
  id: number
  code: string | null
  name: string
}

/** Shape of every treasury movement response (`data`). */
export interface TreasuryMovement {
  id: number
  type: TreasuryMovementType
  direction: TreasuryDirection | null
  movement_date: string
  amount: string
  account: TreasuryAccountRef
  counterpart_account: TreasuryAccountRef | null
  description: string
  sub_account: TreasurySubAccountRef
  registry: { id: number; name: string } | null
  work_order: { id: number; code: string } | null
  attachments_count: number
  created_by: { id: number; name: string } | null
  created_at: string
  updated_at: string
}

/** Exactly one of the three sub-account ids is set (spec 0360 rules). */
export interface TreasuryMovementPayload {
  type: TreasuryMovementType
  direction: TreasuryDirection | null
  movement_date: string
  financial_account_id: number
  counterpart_account_id: number | null
  amount: string
  description: string
  cost_type_id: number | null
  revenue_type_id: number | null
  treasury_type_id: number | null
  registry_id: number | null
  work_order_id: number | null
}

/** One selectable sub-account, whichever source it comes from. */
export interface SubAccountOption {
  kind: SubAccountKind
  id: number
  label: string
}

/** Sub-account kinds allowed per movement type (spec 0360 rules). */
export const SUB_ACCOUNT_KINDS_BY_TYPE: Record<TreasuryMovementType, readonly SubAccountKind[]> = {
  income: ['revenue', 'financial'],
  expense: ['cost', 'financial'],
  transfer: ['financial'],
  adjustment: ['financial'],
}
