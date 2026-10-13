/**
 * Business Functions CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely business-functions-specific — the resource and its
 * create/update payloads. Source of truth: spec 0010 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * Mutually exclusive domain type: the form exposes a single `type` selector
 * (business unit / business service / none) which the backend maps onto the
 * two boolean columns (`is_business_unit`/`is_business_service`).
 */
export const BUSINESS_FUNCTION_TYPES = ['business_unit', 'business_service'] as const

export type BusinessFunctionType = (typeof BUSINESS_FUNCTION_TYPES)[number]

/** A manager or associated-user reference as returned by the backend. */
export interface BusinessFunctionMember {
  id: number
  name: string
  avatar_url: string | null
}

/** The parent business function reference, hydrated on the detail (hierarchy). */
export interface BusinessFunctionParent {
  id: number
  name: string
}

/** An operational site reference, hydrated on the detail: `label` is `"line1 - city"`. */
export interface BusinessFunctionOperationalSite {
  id: number
  label: string
}

/**
 * Single business-function detail returned by GET/POST/PATCH
 * /business-functions (envelope `data`). Matches `BusinessFunctionResource`.
 */
export interface BusinessFunctionDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  name: string
  is_business_unit: boolean
  is_business_service: boolean
  type: BusinessFunctionType | null
  manager_id: number | null
  /** Hydrates the single-select responsabile control. */
  manager: BusinessFunctionMember | null
  user_ids: number[]
  /** Hydrates the multiselect associated-users control. */
  users: BusinessFunctionMember[]
  parent_id: number | null
  /** Hydrates the single-select parent-function picker. */
  parent: BusinessFunctionParent | null
  operational_site_ids: number[]
  /** Hydrates the multiselect operational-sites control. */
  operational_sites: BusinessFunctionOperationalSite[]
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `BusinessFunctionDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /business-functions/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface BusinessFunctionDetailWithPermissions extends BusinessFunctionDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /business-functions (create). */
export interface CreateBusinessFunctionPayload {
  name: string
  type: BusinessFunctionType | null
  manager_id: number | null
  users: number[]
  parent_id: number | null
  operational_sites: number[]
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /business-functions/{id} (partial update). Every field is
 * optional so the request only carries what actually changed: `users` is a
 * full-replace sync, `manager_id: null` clears the responsabile, `type`
 * re-maps the two boolean columns.
 */
export type UpdateBusinessFunctionPayload = Partial<CreateBusinessFunctionPayload>

/** Discriminated form mode: create (a blank draft) or edit (the detail's in-place rows). */
export type BusinessFunctionFormMode =
  | { type: 'create' }
  | { type: 'edit'; businessFunction: BusinessFunctionDetailWithPermissions }
