/**
 * Projects CRUD types. The generic table types (columns/filters/actions/rows)
 * live in `features/table/types.ts`; this file holds only what is genuinely
 * projects-specific. Source of truth: spec 0023 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { GeoScope } from '@/features/geo/geo-scope'
import type { ProductLine } from '@/features/product-lines/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'

/** Hydrated projection of a plain `{id, name}` relation (business_function/state/product_category/partner). */
export interface ProjectRelationRef {
  id: number
  name: string
}

/**
 * The linked operational site's identity, as exposed by `ProjectResource.operational_site`.
 * `operational_sites` has no `name` column (mirrors `LeadOperationalSiteRef`):
 * the identity is a server-composed "{line1} - {city}" label.
 */
export interface ProjectOperationalSiteRef {
  id: number
  label: string
}

/** Hydrated projection of the project's status, carrying its display color token. */
export interface PipelineStatusRef {
  id: number
  name: string
  color: string | null
}

/**
 * Single project detail returned by GET/POST/PATCH /projects (envelope
 * `data`). Matches `ProjectResource`. `total_budget`/`allocated_budget`/
 * `remaining_budget` are `decimal(15,2)` columns cast `decimal:2` server-side
 * (Laravel serializes a decimal cast as a numeric STRING, never a JS number —
 * mirrors the `total_budget`/`allocated_budget`/`remaining_budget` shape
 * frozen for the `/projects/for-select` `meta` block in the same contract).
 */
export interface ProjectDetail {
  id: number
  code: string
  name: string
  description: string | null
  pipeline_status_id: number
  pipeline_status: PipelineStatusRef
  /** Geo cascade (spec 0027 BR-4): `country_id` is required server-side, the other three optional. */
  country_id: number | null
  country: ProjectRelationRef | null
  state_id: number | null
  state: ProjectRelationRef | null
  province_id: number | null
  province: ProjectRelationRef | null
  city_id: number | null
  city: ProjectRelationRef | null
  /** Finest non-null geo level, derived server-side (spec 0027 D-2). Never re-derived here. */
  geo_scope: GeoScope | null
  /**
   * Business-function + product-category row collection (spec 0094, replaces
   * the former single `business_function_id`/`product_category_id` scalar
   * pair): the shared `ProductLine` shape, identical to the opportunity's own
   * (`ProductLinesField`, spec 0057).
   */
  product_lines: ProductLine[]
  partner_id: number | null
  partner: ProjectRelationRef | null
  /** The Sede inherited by every campaign/lead created under this project (prefill, not a lock). */
  operational_site_id: number | null
  operational_site: ProjectOperationalSiteRef | null
  start_date: string | null
  end_date: string | null
  total_budget: string | null
  target_lead: number | null
  /** Sum of the linked campaigns' `total_budget` (BR-7). */
  allocated_budget: string
  /** `total_budget - allocated_budget`, `null` when `total_budget` is unset (BR-7). */
  remaining_budget: string | null
  /** Number of campaigns linked to this project (drives the delete guard, BR-5/AC-016). */
  campaigns_count: number
  created_at: string
  /** Custom field values keyed by their raw (un-namespaced) key (spec 0021). */
  custom_fields?: Record<string, CustomFieldValue>
}

/**
 * A `ProjectDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /projects/{id}`. Used to seed the
 * edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface ProjectDetailWithPermissions extends ProjectDetail {
  permissions: ResourcePermissions
}

/** A `product_lines` row as sent to the server (create/update payload, spec 0094). */
export interface ProjectProductLineInput {
  business_function_id: number
  product_category_id: number
}

/**
 * Payload for POST /projects (create). `code` is optional and manual
 * (spec 0025): omitted or empty falls back to server-side sequential
 * generation (`PRJ-xxxx`); PATCH never accepts it (immutable after create).
 * `pipeline_status_id` is nullable/optional (spec 0039 D-3): the server
 * falls back to the system "Nuovo" status when omitted. `product_lines` is
 * REQUIRED (min 1, spec 0094): the server replaces the entire row set on
 * every write, so it is always sent in full, even on a bare create.
 */
export interface CreateProjectPayload {
  code?: string
  name: string
  pipeline_status_id?: number | null
  description?: string | null
  product_lines: ProjectProductLineInput[]
  /** Geo cascade (spec 0027 BR-4): `country_id` is required on create. */
  country_id?: number | null
  state_id?: number | null
  province_id?: number | null
  city_id?: number | null
  partner_id?: number | null
  operational_site_id?: number | null
  start_date?: string | null
  end_date?: string | null
  total_budget?: number | null
  target_lead?: number | null
  /** All valued custom fields, keyed by raw key (spec 0021, create = full set). */
  custom_fields?: Record<string, CustomFieldValue>
}

/** Payload for PATCH /projects/{id} (partial update). Every field is optional (sparse diff). */
export type UpdateProjectPayload = Partial<CreateProjectPayload>

/** Discriminated form mode shared by the form hook/meta-resolver and `ProjectForm`. */
export type ProjectFormMode =
  | { type: 'create' }
  | { type: 'edit'; project: ProjectDetailWithPermissions }
  /** Create form pre-filled from `source` (row action "duplicate"): still submits via the create path. */
  | { type: 'duplicate'; source: ProjectDetail }

/** Per-card action affordances, computed server-side with the Gate (spec 0026 BR-2). */
export interface ProjectCardPermissions {
  update: boolean
  delete: boolean
}

/**
 * Single project card as returned by `GET /projects` (spec 0026), the plain
 * index endpoint powering the card grid. A different payload than
 * `ProjectDetail`/the AG Grid `TableRow`: no relations besides `pipeline_status`.
 */
export interface ProjectCard {
  id: number
  code: string
  name: string
  description: string | null
  pipeline_status: PipelineStatusRef | null
  campaigns_count: number
  leads_count: number
  /** Finest non-null geo level, derived server-side (spec 0027 D-2). Never re-derived here. */
  geo_scope: GeoScope | null
  /** The `geo_scope` level's place name (e.g. "Milano"), ready-made so the card avoids a second request. */
  geo_label: string | null
  total_budget: string | null
  allocated_budget: string
  remaining_budget: string | null
  start_date: string | null
  end_date: string | null
  can: ProjectCardPermissions
}

/** Query params accepted by `GET /projects` (spec 0026). */
export interface ProjectCardListParams {
  search?: string
  offset?: number
  limit?: number
  pipeline_status_id?: number
  /**
   * Active advanced filters (spec 0032 AC-018), keyed like
   * `TableConfig.appliedAdvancedFilters` for the `projects` domain — the same
   * shape sent to `POST /tables/projects/rows` by the AG Grid view, so both
   * views filter identically off one shared, backend-persisted state.
   */
  advancedFilters?: AdvancedFilterValues
}
