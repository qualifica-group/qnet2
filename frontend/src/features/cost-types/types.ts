/**
 * Cost types CRUD types. Source of truth: the frozen `data_contract` of specs
 * 0210 (`cost_types`) and 0249 (nature, color, inline edit, reorder).
 * Configurable lookup chosen on every purchase order line.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const COST_TYPES_DOMAIN = 'cost-types'

/** Fixed nature list (spec 0249 D-1): labels live in i18n under `costTypes.nature.<value>`. */
export const COST_TYPE_NATURES = ['direct', 'indirect', 'general'] as const
export type CostTypeNature = (typeof COST_TYPE_NATURES)[number]

/** Single cost type returned by GET/POST/PATCH /cost-types (envelope `data`). */
export interface CostTypeDetail {
  id: number
  name: string
  description: string | null
  nature: CostTypeNature
  /** Palette token of `BADGE_COLOR_TOKENS`, never a hex. */
  color: string
  /** Server-managed (reorder endpoint): read-only, never accepted on write. */
  sort_order: number
  is_active: boolean
  created_at: string
  updated_at: string
  abilities: { update: boolean; delete: boolean }
}

/**
 * A `CostTypeDetail` carrying the authorization metadata the detail's
 * `ResourcePermissionsProvider` reads: the envelope `permissions` when the
 * backend sends them, otherwise derived from `abilities`.
 */
export interface CostTypeDetailWithPermissions extends CostTypeDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /cost-types. `sort_order` is refused by the backend (422). */
export interface CostTypePayload {
  name: string
  description: string | null
  nature: CostTypeNature
  color: string
  is_active: boolean
}

/** Payload for PATCH /cost-types/{id}: only the fields that changed. */
export type UpdateCostTypePayload = Partial<CostTypePayload>

/** Discriminated form mode shared by the form hook and the detail/create components. */
export type CostTypeFormMode =
  | { type: 'create' }
  | { type: 'edit'; costType: CostTypeDetailWithPermissions }
