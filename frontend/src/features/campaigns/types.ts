/**
 * Campaigns CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * campaigns-specific. Source of truth: spec 0023 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ProjectOperationalSiteRef, ProjectRelationRef, PipelineStatusRef } from '@/features/projects/types'
import type { GeoScope } from '@/features/geo/geo-scope'
import type { ProductLine } from '@/features/product-lines/types'

/** Hydrated `{id, name}` relation shared by partner/business_function/state/product_category (identical shape to a project's, reused rather than redeclared). */
export type CampaignRelationRef = ProjectRelationRef

/** The linked operational site's identity, as exposed by `CampaignResource.operational_site` (identical shape to a project's, reused rather than redeclared). */
export type CampaignOperationalSiteRef = ProjectOperationalSiteRef

/** The linked project's minimal identity, as exposed by `CampaignResource.project`. */
export interface CampaignProjectRef {
  id: number
  code: string
  name: string
}

/**
 * Single campaign detail returned by GET/POST/PATCH /campaigns (envelope
 * `data`). Matches `CampaignResource`. Per BR-2, when `project_id` is set the
 * 3 classification fields (`pipeline_status`/`business_function`/
 * `product_category`) are NULL in DB but this shape always carries the
 * EFFECTIVE values (read through the project) plus `derived_from_project`, so
 * the form/detail never need to special-case a linked campaign's data source.
 * Per BR-5 (spec 0027, replaces BR-2 for geo), the 4 geo fields carry the
 * EFFECTIVE (merged `campaign.<level> ?? project.<level>`) tuple, and
 * `geo_locked_levels` lists which of those levels are owned by the linked
 * project (not stored on this row).
 */
export interface CampaignDetail {
  id: number
  code: string
  project_id: number | null
  project: CampaignProjectRef | null
  name: string
  description: string | null
  partner_id: number | null
  partner: CampaignRelationRef | null
  /** The campaign's own Sede: prefilled (never locked) from the linked project's on selection, always editable (directive: project -> campaign -> lead prefill chain). */
  operational_site_id: number | null
  operational_site: CampaignOperationalSiteRef | null
  derived_from_project: boolean
  pipeline_status_id: number | null
  pipeline_status: PipelineStatusRef | null
  /** Geo cascade (spec 0027 BR-4/BR-5): EFFECTIVE (merged) values, `country_id` required only when standalone or the project has none. */
  country_id: number | null
  country: CampaignRelationRef | null
  state_id: number | null
  state: CampaignRelationRef | null
  province_id: number | null
  province: CampaignRelationRef | null
  city_id: number | null
  city: CampaignRelationRef | null
  /** Finest non-null geo level of the effective tuple, derived server-side (D-2). Never re-derived here. */
  geo_scope: GeoScope | null
  /** The geo levels owned by the linked project (BR-5): `prohibited` on write, not stored on this row. */
  geo_locked_levels: GeoScope[]
  /**
   * Business-function + product-category row collection (spec 0094, replaces
   * the former single `business_function_id`/`product_category_id` scalar
   * pair). Per BR-2, these are always the EFFECTIVE rows: the campaign's own
   * when standalone, the linked project's when `derived_from_project`.
   */
  product_lines: ProductLine[]
  start_date: string | null
  end_date: string | null
  total_budget: string | null
  target_lead: number | null
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `CampaignDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /campaigns/{id}`. Used to seed the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface CampaignDetailWithPermissions extends CampaignDetail {
  permissions: ResourcePermissions
}

/** A `product_lines` row as sent to the server (create/update payload, spec 0094). */
export interface CampaignProductLineInput {
  business_function_id: number
  product_category_id: number
}

/**
 * Payload for POST /campaigns (create). `code` is optional and manual
 * (spec 0025): omitted or empty falls back to server-side sequential
 * generation (`CMP-xxxx`); PATCH never accepts it (immutable after create).
 * `pipeline_status_id` and `product_lines` are `required_if project_id ==
 * null` / `prohibited` when linked (BR-2) — enforced client-side by the Zod
 * schema, not by this type; `product_lines` is optional here precisely
 * because it is entirely OMITTED (not sent as `[]`) while linked. The 4 geo
 * fields follow BR-5/BR-4 instead (spec 0027): `country_id` is required
 * unless the linked project already provides one, the rest are optional; any
 * level the project fills is `prohibited` and omitted by the payload
 * builder, never sent regardless of the (disabled) form value.
 */
export interface CreateCampaignPayload {
  code?: string
  name: string
  project_id?: number | null
  description?: string | null
  partner_id?: number | null
  operational_site_id?: number | null
  pipeline_status_id?: number | null
  product_lines?: CampaignProductLineInput[]
  country_id?: number | null
  state_id?: number | null
  province_id?: number | null
  city_id?: number | null
  start_date?: string | null
  end_date?: string | null
  total_budget?: number | null
  target_lead?: number | null
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Payload for PATCH /campaigns/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateCampaignPayload = Partial<CreateCampaignPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `CampaignForm`. */
export type CampaignFormMode =
  | { type: 'create' }
  | { type: 'edit'; campaign: CampaignDetailWithPermissions }
  /** Create form pre-filled from `source` (row action "duplicate"): still submits via the create path. */
  | { type: 'duplicate'; source: CampaignDetail }
