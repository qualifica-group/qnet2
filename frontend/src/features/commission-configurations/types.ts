import type { ResourcePermissions } from '@/features/authorization/types'

export const COMMISSION_ROLES = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER'] as const
export const COMMISSION_SCOPES = ['PRODUCT_CATEGORY', 'PRODUCT'] as const
export const COMMISSION_TYPES = ['FIXED_AMOUNT', 'PERCENTAGE'] as const
export const COMMISSION_STATUSES = ['ACTIVE', 'SUSPENDED'] as const

export type CommissionRole = (typeof COMMISSION_ROLES)[number]
export type CommissionScope = (typeof COMMISSION_SCOPES)[number]
export type CommissionType = (typeof COMMISSION_TYPES)[number]
export type CommissionStatus = (typeof COMMISSION_STATUSES)[number]

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
