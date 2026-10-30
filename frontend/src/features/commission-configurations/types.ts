import type { ResourcePermissions } from '@/features/authorization/types'

export const COMMISSION_ROLES = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER'] as const
export const COMMISSION_SCOPES = ['PRODUCT_CATEGORY', 'PRODUCT', 'RECIPIENT'] as const
export const COMMISSION_TYPES = ['FIXED_AMOUNT', 'PERCENTAGE'] as const
export const COMMISSION_STATUSES = ['ACTIVE', 'SUSPENDED'] as const
/** Morph alias enforced by `Relation::enforceMorphMap()` (spec 0089 D-1) — the only valid `recipient_type` values. */
export const COMMISSION_RECIPIENT_TYPES = ['referent', 'user', 'registry'] as const

/** Direction of the Supplier commission (spec 0306 D-1): earned by us (RECEIVED) or paid to the supplier (PAID). */
export const SUPPLIER_COMMISSION_DIRECTIONS = ['RECEIVED', 'PAID'] as const

export type CommissionRole = (typeof COMMISSION_ROLES)[number]
export type CommissionScope = (typeof COMMISSION_SCOPES)[number]
export type CommissionType = (typeof COMMISSION_TYPES)[number]
export type CommissionStatus = (typeof COMMISSION_STATUSES)[number]
export type CommissionRecipientType = (typeof COMMISSION_RECIPIENT_TYPES)[number]
export type SupplierCommissionDirection = (typeof SUPPLIER_COMMISSION_DIRECTIONS)[number]

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

/** The names the closed rows show for the three relations: persisted on a detail, resolved from the draft's ids on create. */
export interface CommissionConfigurationRefs {
  category: CommissionRelationRef | null
  product: CommissionRelationRef | null
  recipient: CommissionRelationRef | null
}

export interface CommissionConfigurationDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
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
  /** Set only on a SUPPLIER rule; omitted when not visible for field permission (spec 0306 D-9). */
  supplier_commission_direction?: SupplierCommissionDirection | null
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
  /** Mandatory with a SUPPLIER role, always null for the other roles (spec 0306 D-1). */
  supplier_commission_direction: SupplierCommissionDirection | null
  value: number
  priority: number
  valid_from: string
  valid_until: string | null
  status: CommissionStatus
  internal_note: string | null
}

export type UpdateCommissionConfigurationPayload = Partial<CommissionConfigurationPayload>
/** The create param the anagrafica detail seeds the form with (spec 0204 D-4, same key as spec 0199). */
export const COMMISSION_CREATE_REGISTRY_PARAM = 'registry_id'

/** The create param the referente detail seeds the form with (spec 0336 D-4). */
export const COMMISSION_CREATE_REFERENT_PARAM = 'referent_id'

/**
 * The personal rule a create starts as, when opened from a recipient's
 * "Commissioni configurate": SUPPLIER on an anagrafica (spec 0204 D-4),
 * REPORTER on a referente (spec 0336 D-4). The recipient type is the role's default.
 */
export interface CommissionRecipientPreset {
  role: CommissionRole
  recipientId: number
}

export type CommissionConfigurationFormMode =
  | { type: 'create'; recipientPreset?: CommissionRecipientPreset | null }
  | { type: 'edit'; configuration: CommissionConfigurationDetailWithPermissions }

/** How a recalculation treats the commissions changed by hand (spec 0308 D-2). */
export type CommissionRecalculationMode = 'KEEP_MANUAL' | 'REGENERATE_ALL'

/** What a saved rule would touch on the quotes that use it (spec 0308 D-6). */
export interface CommissionConfigurationQuoteImpact {
  quotes_count: number
  lines_count: number
  manual_commissions_count: number
  can_apply: boolean
}
