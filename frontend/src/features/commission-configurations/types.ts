import type { ResourcePermissions } from '@/features/authorization/types'

export const COMMISSION_ROLES = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER'] as const
export const COMMISSION_SCOPES = ['PRODUCT_CATEGORY', 'PRODUCT', 'RECIPIENT'] as const
export const COMMISSION_TYPES = ['FIXED_AMOUNT', 'PERCENTAGE'] as const
export const COMMISSION_STATUSES = ['ACTIVE', 'SUSPENDED'] as const
/** Morph alias enforced by `Relation::enforceMorphMap()` (spec 0089 D-1) — the only valid `recipient_type` values. */
export const COMMISSION_RECIPIENT_TYPES = ['referent', 'user', 'registry'] as const

export type CommissionRole = (typeof COMMISSION_ROLES)[number]
export type CommissionScope = (typeof COMMISSION_SCOPES)[number]
export type CommissionType = (typeof COMMISSION_TYPES)[number]
export type CommissionStatus = (typeof COMMISSION_STATUSES)[number]
export type CommissionRecipientType = (typeof COMMISSION_RECIPIENT_TYPES)[number]

export interface CommissionRelationRef {
  id: number
  name: string
}

export interface CommissionConfigurationDetail {
  id: number
  name: string
  recipient_role: CommissionRole
  application_scope: CommissionScope
  product_category_id: number | null
  product_category: CommissionRelationRef | null
  product_id: number | null
  product: CommissionRelationRef | null
  /**
   * Sola lettura, derivato da `recipient_role` (D-7). `null` per una regola di
   * ruolo; le tre chiavi sono OMESSE (non `null`) quando `recipient_id` non e'
   * visibile per field permission — stessa regola di `product_id`/`product`.
   */
  recipient_type?: CommissionRecipientType | null
  recipient_id?: number | null
  recipient?: CommissionRelationRef | null
  commission_type: CommissionType
  value: string
  priority: number
  valid_from: string
  valid_until: string | null
  status: CommissionStatus
  internal_note: string | null
  created_at: string
  updated_at: string
}

export interface CommissionConfigurationDetailWithPermissions
  extends CommissionConfigurationDetail {
  permissions: ResourcePermissions
}

export interface CommissionConfigurationPayload {
  name: string
  recipient_role: CommissionRole
  application_scope: CommissionScope
  product_category_id: number | null
  product_id: number | null
  /** Optional (D-1): a role rule never sends it. `recipient_type` is never accepted — the server derives it from `recipient_role` (D-7). */
  recipient_id?: number | null
  commission_type: CommissionType
  value: number
  priority: number
  valid_from: string
  valid_until: string | null
  status: CommissionStatus
  internal_note: string | null
}

export type UpdateCommissionConfigurationPayload = Partial<CommissionConfigurationPayload>
export type CommissionConfigurationFormMode =
  | { type: 'create' }
  | { type: 'edit'; configuration: CommissionConfigurationDetailWithPermissions }
