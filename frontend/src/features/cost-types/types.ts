/**
 * Cost types CRUD types. Source of truth: the frozen `data_contract` of spec
 * 0210 (`cost_types`). Configurable lookup chosen on every purchase order line.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const COST_TYPES_DOMAIN = 'cost-types'

/** Single cost type returned by GET/POST/PUT /cost-types (envelope `data`). */
export interface CostTypeDetail {
  id: number
  name: string
  description: string | null
  sort_order: number
  is_active: boolean
  created_at: string
  updated_at: string
  abilities: { update: boolean; delete: boolean }
}

/**
 * A `CostTypeDetail` carrying the authorization metadata the edit form's
 * `ResourcePermissionsProvider` reads: the envelope `permissions` when the
 * backend sends them, otherwise derived from `abilities`.
 */
export interface CostTypeDetailWithPermissions extends CostTypeDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /cost-types and PUT /cost-types/{id} (same shape). */
export interface CostTypePayload {
  name: string
  description: string | null
  sort_order: number
  is_active: boolean
}

/** Discriminated form mode shared by the form hook/meta-resolver and the form component. */
export type CostTypeFormMode =
  | { type: 'create' }
  | { type: 'edit'; costType: CostTypeDetailWithPermissions }
