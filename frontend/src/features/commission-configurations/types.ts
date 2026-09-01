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

/**
 * Recipient types admitted for each role (spec 0090 D-4, emenda 0089 D-7),
 * single source of truth for both the form's type selector and its
 * validation. Order matters: the FIRST entry is the server-side default when
 * `recipient_type` is omitted (`CommissionRecipientRole::recipientType()`),
 * so it must match that enum's mapping exactly.
 */
export const COMMISSION_ROLE_ALLOWED_RECIPIENT_TYPES: Record<
  CommissionRole,
  readonly CommissionRecipientType[]
> = {
  COMMERCIAL: ['referent', 'user'],
  REPORTER: ['referent', 'user'],
  SUPERVISOR: ['user', 'referent'],
  SUPPLIER: ['registry'],
}

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
   * The recipient's identity type, chosen by the operator among the role's
   * allow-list (spec 0090 D-4, emenda 0089 D-7); `null` for a role rule. The
   * three keys are OMITTED (not `null`) when `recipient_id` is not visible
   * for field permission — same rule as `product_id`/`product`.
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
  /**
   * The identity type of `recipient_id`, chosen among the role's allow-list
   * (spec 0090 D-4, emenda 0089 D-7); omitted -> the server derives it from
   * `recipient_role`, identical to before this spec.
   */
  recipient_type?: CommissionRecipientType | null
  /** Optional (D-1): a role rule never sends it. */
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
