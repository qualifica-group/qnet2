/**
 * Companies CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * companies-specific — the resource, its single polymorphic address and its
 * create/update payloads. Source of truth: the frozen spec 0010 `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * The company's single address (ADR 0010), as returned by `CompanyResource`.
 * The geo ids drive the cascading `GeoSelect`; the names (`country`/`region`/
 * `province`/`city`) are read-only labels used by the detail view.
 */
export interface CompanyAddress {
  id: number
  line1: string
  line2: string | null
  postal_code: string | null
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
  country: string | null
  region: string | null
  province: string | null
  city: string | null
  is_primary: boolean
}

/**
 * Single company detail returned by GET/POST/PATCH /companies (envelope
 * `data`). Matches `CompanyResource`. `address` is `null` for a company
 * created without one (a company has at most one address — spec 0010 scope).
 */
export interface CompanyDetail {
  id: number
  /** Legacy id; sent only to an actor allowed to see it (`legacy-ids.view`). */
  old_id?: number | null
  denomination: string
  vat_number: string | null
  address: CompanyAddress | null
  created_at: string | null
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `CompanyDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /companies/{company}` (`show`).
 * Used to seed the edit form's `ResourcePermissionsProvider` without a second
 * request.
 */
export interface CompanyDetailWithPermissions extends CompanyDetail {
  permissions: ResourcePermissions
}

/** The nested address block accepted by POST/PATCH /companies. */
export interface CreateCompanyAddressPayload {
  line1: string
  line2?: string | null
  postal_code?: string | null
  country_id?: number | null
  state_id?: number | null
  province_id?: number | null
  city_id?: number | null
}

/** Payload for POST /companies (create). */
export interface CreateCompanyPayload {
  denomination: string
  vat_number?: string | null
  address?: CreateCompanyAddressPayload
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /companies/{id} (partial update). Every field is optional
 * so the request only carries what actually changed; a present `address`
 * fully rewrites the company's single address (update if it already exists,
 * create if absent — handled server-side by `AddressService`).
 */
export interface UpdateCompanyPayload {
  denomination?: string
  vat_number?: string | null
  address?: CreateCompanyAddressPayload
  /** Only the custom fields that changed, keyed by raw key (spec 0021, sparse diff). */
  custom_fields?: Record<string, CustomFieldValue>
}
