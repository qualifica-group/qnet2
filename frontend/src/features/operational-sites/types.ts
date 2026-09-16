/**
 * Operational Sites CRUD types. The generic table types (columns/filters/
 * actions/rows) live in `features/table/types.ts`; this file holds only what
 * is genuinely operational-sites-specific — the resource and its
 * create/update payloads. Source of truth: spec 0011 frozen `data_contract`.
 *
 * An operational site has no own name/label: it IS its address. The address
 * fields (line1/postal_code + geo ids) live directly on the resource shape
 * returned by the backend (embedded from the primary `Address`), never as a
 * nested `address` object — mirroring the frozen `show`/create/update contract.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'

/** A geo reference `{id, name}` as hydrated on the site detail (country/region/province/city). */
export interface GeoReference {
  id: number
  name: string
}

/**
 * Single operational site detail returned by GET/POST/PATCH
 * /operational-sites (envelope `data`). Matches `OperationalSiteResource`.
 */
export interface OperationalSiteDetail {
  id: number
  /** The site's own free-text label (legacy `comune` when migrated). */
  alias: string | null
  line1: string
  postal_code: string | null
  country_id: number | null
  country: GeoReference | null
  state_id: number | null
  region: GeoReference | null
  province_id: number | null
  province: GeoReference | null
  city_id: number | null
  city: GeoReference | null
  /** An inactive site is no longer offered by `operational-sites/for-select` (spec 0135). */
  is_active: boolean
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * An `OperationalSiteDetail` carrying the actor's authorization metadata for
 * this instance (spec 0004), as returned by `GET /operational-sites/{id}`
 * (`show`). Used to seed the edit form's `ResourcePermissionsProvider`
 * without a second request.
 */
export interface OperationalSiteDetailWithPermissions extends OperationalSiteDetail {
  permissions: ResourcePermissions
}

/** Payload for POST /operational-sites (create). */
export interface CreateOperationalSitePayload {
  alias: string | null
  line1: string
  postal_code: string | null
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number
  is_active: boolean
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * Payload for PATCH /operational-sites/{id} (partial update). Every field is
 * optional so the request only carries what actually changed:
 * `postal_code: null` clears the CAP.
 */
export type UpdateOperationalSitePayload = Partial<CreateOperationalSitePayload>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `OperationalSiteForm` component, mirroring the `business-functions`
 * convention.
 */
export type OperationalSiteFormMode =
  | { type: 'create' }
  | { type: 'edit'; operationalSite: OperationalSiteDetailWithPermissions }
