/**
 * Opportunities CRUD types. The generic table types (columns/filters/actions/
 * rows) live in `features/table/types.ts`; this file holds only what is
 * genuinely opportunities-specific. Source of truth: spec 0040 frozen
 * `data_contract`. Supervisor is required by the create form but remains
 * nullable on stored opportunities and in edit mode.
 */

import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ProductLine } from '@/features/product-lines/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** A hydrated `{id, name}` relation projection, shared by every plain single-relation field. */
export interface OpportunityRelationRef {
  id: number
  name: string
}

/**
 * One distinct status inside an `OpportunityStatusSummary` (spec 0083), with
 * how many quotes sit in it (`count: 0` on the zero-quotes default fallback).
 */
export interface OpportunityStatusEntry {
  id: number
  name: string
  color: string | null
  group: string
  count: number
}

/**
 * The COMPUTED status (spec 0083, ex spec 0082), as exposed by
 * `OpportunityResource.status`, the `status` grid cell, the
 * request-management panel and the reward card. `source` says which
 * vocabulary `entries` speaks: `'quotes'` when the opportunity has at least
 * one quote (one entry per distinct `quote_workflow_status_id`), `'default'`
 * when it falls back to the `open` row of the global default set. Zero
 * quotes always resolves exactly one entry; `distinct_count > 1` is the
 * "N stati" case.
 */
export interface OpportunityStatusSummary {
  source: 'quotes' | 'default'
  distinct_count: number
  entries: OpportunityStatusEntry[]
}

/** The linked lead's identity, as exposed by `OpportunityResource.lead` (the lead's referent name, BR-1). */
export interface OpportunityLeadRef {
  id: number
  label: string
}

/**
 * Spec 0056: the linked operational site's identity, as exposed by
 * `OpportunityResource.operational_site`. `operational_sites` has no `name`
 * column: the identity is a server-composed "{line1} - {city}" label (mirrors
 * `ProjectOperationalSiteRef`). Kept LOCAL rather than imported from
 * `features/projects` to keep the two modules decoupled (same reasoning as
 * `ApplicableAttributeSummary` above).
 */
export interface OpportunityOperationalSiteRef {
  id: number
  label: string
}

/** A manager ref carrying its static "G.A. n" `position` (1-based) on top of the person ref. */
export interface OpportunityManagerRef {
  id: number
  name: string
  position: number
}

/** A single labeled choice of an enum-type Attribute (spec 0049). */
export interface AttributeOptionRef {
  value: string
  label: string
  color: string | null
}

/**
 * Spec 0049 (D-8, `data_contract` "OPPORTUNITA' (additivo)"): summary of ONE
 * Attribute applicable to this opportunity — the union, dedup-per-`code`, of
 * the effective Attributes of every product-category row — as exposed by
 * `OpportunityResource.applicable_attributes`. Kept LOCAL rather than imported
 * from `features/request-management` to keep the two modules decoupled; it
 * mirrors the same shape by frozen contract, not by import.
 */
export interface ApplicableAttributeSummary {
  id: number
  code: string
  name: string
  type: string
  description: string | null
  help_text: string | null
  placeholder: string | null
  icon: string | null
  config: Record<string, unknown> | null
  relation_target: Record<string, unknown> | null
  is_required: boolean
  sort_order: number
  options: AttributeOptionRef[]
}

/**
 * Wire shape of POST /api/opportunities/form-context (user directive
 * 2026-08-05): the dynamic fields the criteria typed so far resolve to, for
 * the CREATE form — the same endpoint/response request-management already
 * exposes.
 */
export interface OpportunityFormContext {
  applicable_attributes: ApplicableAttributeSummary[]
  attribute_layout: LayoutBlob | null
}

/**
 * A confirmed business-function + product-category pair (spec 0040 amendment
 * rev.3, AC-097/098/101): replaces the former single `business_function_id`/
 * `product_category_id` columns with a one-to-many collection of rows. Spec
 * 0057 generalized the shape into the shared `ProductLine` (identical fields)
 * so it can be reused outside the opportunity form; kept as a local alias so
 * every existing consumer in this feature stays unchanged.
 */
export type OpportunityProductLine = ProductLine

/**
 * A product recorded as "di interesse" (user directive 2026-07-22), with its
 * own category — identical shape to the request-management projection.
 */
export interface OpportunityProductOfInterest {
  id: number
  name: string
  product_category: OpportunityRelationRef | null
}

/** A product-line row as sent to the server (create/update payload, AC-099). */
export interface OpportunityProductLineInput {
  business_function_id: number
  product_category_id: number
}

/**
 * One `rewards` row of the create/update payload (spec 0059 §4): only the
 * type id travels — the beneficiary (the reporter) and `assigned_at` are
 * derived server-side by `RewardAssignmentWriter`.
 */
export interface OpportunityRewardInput {
  reward_type_id: number
}

/**
 * Single opportunity detail returned by GET/POST/PATCH /opportunities
 * (envelope `data`). Matches `OpportunityResource`.
 */
export interface OpportunityDetail {
  id: number
  name: string
  registry_id: number
  registry: OpportunityRelationRef | null
  /** Spec 0083: the status COMPUTED from the quotes (global default 'open' row as fallback). Read-only. */
  status: OpportunityStatusSummary
  referent_id: number | null
  referent: OpportunityRelationRef | null
  commercial_id: number | null
  commercial: OpportunityRelationRef | null
  reporter_id: number | null
  reporter: OpportunityRelationRef | null
  supervisor_id: number | null
  supervisor: OpportunityRelationRef | null
  source_id: number | null
  source: OpportunityRelationRef | null
  /**
   * Spec 0056: the operational site, facoltativa, never lead-derived (no BR-1
   * inheritance, no `locked_fields` entry). Optional for the same
   * fixture-compatibility reason as `state` below — treat a missing key the
   * same as `null`.
   */
  operational_site_id?: number | null
  operational_site?: OpportunityOperationalSiteRef | null
  /**
   * Spec 0047 (D1, AC-003): the Regione, ereditata dal lead alla conversione
   * ma sempre editabile (mai BR-2-locked). Optional so every pre-existing
   * `OpportunityDetail` fixture across this feature's test suites keeps
   * type-checking unchanged (mirrors `LeadDetail.opportunity`); treat a
   * missing key the same as `null`.
   */
  state_id?: number | null
  state?: OpportunityRelationRef | null
  /** Amendment rev.3: replaces the former single `product_category`/`business_function` pair (AC-101). */
  product_lines: OpportunityProductLine[]
  /**
   * "Prodotti di interesse" (user directive 2026-07-22): the products
   * recorded for this opportunity, collected in Gestione Richieste and shown
   * here read-only + editable in the form. Optional for the same
   * fixture-compatibility reason as `state` — treat a
   * missing key the same as `[]`.
   */
  products_of_interest?: OpportunityProductOfInterest[]
  lead_id: number | null
  lead: OpportunityLeadRef | null
  /** Filled manager ids as ordered "G.A. n" cards (name + position), mirrors registries. */
  managers: OpportunityManagerRef[]
  start_date: string | null
  /** Decimal(15,2), as `projects.total_budget`: the server may serialize it as a numeric string. */
  estimated_value: string | number | null
  expected_close_date: string | null
  success_probability: number | null
  /**
   * "Note generali" (user directive 2026-07-27): free text, inherited from
   * the originating lead's `notes` at conversion but always editable. Optional
   * for the same fixture-compatibility reason as `state`
   * above — treat a missing key the same as `null`.
   */
  general_notes?: string | null
  /**
   * BR-2: keys of the fields whose value was derived from the linked Lead's
   * campaign and is therefore locked (immutable, even server-side). Empty
   * when `lead_id` is null.
   */
  locked_fields: string[]
  created_at: string
  updated_at: string
  /**
   * Spec 0049 (D-8): opportunity-level dynamic field values collected by the
   * "Gestione Richieste" module, keyed by Attribute `code`; `{}` when none.
   * Optional for the same fixture-compatibility reason as `state` above —
   * treat a missing key the same as `{}`.
   */
  attribute_values?: Record<string, unknown>
  /**
   * Spec 0049 (D-8): the union (dedup per `code`) of the effective Attributes
   * of every product-category row, feeding `attribute_values`'s labels in the
   * read-only "Informazioni aggiuntive" section (`opportunity-detail.tsx`).
   * Optional for the same fixture-compatibility reason; treat missing as `[]`.
   */
  applicable_attributes?: ApplicableAttributeSummary[]
  /**
   * Spec 0062: the merged, multi-category layout the dynamic fields are
   * rendered through (`FormMode::Edit`), `null` when no contributing category
   * configures one — the renderer reads that as "flat". Optional for the same
   * fixture-compatibility reason as the two keys above.
   */
  attribute_layout?: LayoutBlob | null
  /**
   * Spec 0059 D-3: reward assignments belonging to the reporter, ordered by
   * `reward_type.name`. Optional for the same fixture-compatibility reason
   * as `state` above — treat a missing key the same as `[]`.
   */
  rewards?: RewardAssignmentRef[]
  /**
   * Spec 0067 AC-020: number of Quotes linked to this opportunity (`withCount`,
   * 0 when none). Seeds the Quotes panel's counter/empty-state before the
   * grid reports its own live total (D-9). Optional for the same
   * fixture-compatibility reason as `state` above — treat a
   * missing key the same as `0`.
   */
  quotes_count?: number
  /**
   * Spec 0080: G.A. labels resolved from this opportunity's product-line
   * categories (position, as a string key "1".."4" -> label), additive. `{}`
   * when not resolvable (no product line, or several product lines resolving
   * to different labels). Optional for the same fixture-compatibility reason
   * as `state` above — treat a missing key the same as `{}`.
   */
  manager_labels?: Record<string, string>
}

/**
 * An `OpportunityDetail` carrying the actor's authorization metadata for this
 * instance (spec 0004), as returned by `GET /opportunities/{id}`. Used to seed
 * the edit form's `ResourcePermissionsProvider` without a second request.
 */
export interface OpportunityDetailWithPermissions extends OpportunityDetail {
  permissions: ResourcePermissions
}

/**
 * Payload for POST /opportunities (create). The form requires a numeric
 * supervisor in addition to the existing identity fields. `lead_id`, when
 * set, derives several fields server-side (BR-1) — the client must not also
 * send a value for a field whose derivation is non-null (422 `prohibited`).
 */
export interface CreateOpportunityPayload {
  /**
   * Required for a manual create (D-4, enforced by the Zod schema); OMITTED
   * entirely (not merely repeated) when creating from a Lead and the value is
   * derived (BR-1/BR-2: sending it at all is `prohibited`, 422, even if it
   * matches). Optional here to allow that omission — `buildCreatePayload` is
   * the single place that decides whether to include it.
   */
  registry_id?: number
  referent_id?: number | null
  commercial_id?: number | null
  reporter_id?: number | null
  supervisor_id: number
  source_id?: number | null
  /** Spec 0056: facoltativa, never BR-2-locked (no server-side inheritance from another entity). */
  operational_site_id?: number | null
  /** Spec 0047 (D1): the Regione, freely settable on a standalone create; never locked, even from a lead. */
  state_id?: number | null
  lead_id?: number | null
  /** Ordered, gap-aware G.A. slots: index+1 = G.A. n, `null` = empty slot. */
  manager_slots?: (number | null)[]
  start_date?: string | null
  estimated_value?: number | null
  expected_close_date?: string | null
  success_probability?: number | null
  /** "Note generali" (user directive 2026-07-27): free text, never lead-locked — always sent as-is. */
  general_notes?: string | null
  /**
   * User directive 2026-08-05: the dynamic "Informazioni aggiuntive" map,
   * keyed by Attribute `code` — the same key both request-management channels
   * send. Included only when there is something to send: on create when the
   * chosen categories resolve at least one attribute, on update only when a
   * value actually changed (the server merges sparsely either way).
   */
  attribute_values?: Record<string, CustomFieldValue>
  /**
   * Amendment rev.3 (AC-099): the server REPLACES the entire row collection
   * on every write. Always sent in full on create (even empty); the update
   * builder only includes it when the row SET actually changed.
   */
  product_lines: OpportunityProductLineInput[]
  /**
   * "Prodotti di interesse" (user directive 2026-07-22): product ids,
   * AUTHORITATIVE when sent. A product outside the submitted
   * `product_lines` categories is accepted: the server ADDS the matching
   * row, which is what the picker's unlock dialog warns about.
   */
  products_of_interest?: number[]
  /**
   * Spec 0059 D-3/`sync_semantics`: reward assignments for the reporter, a
   * full-replace sync (like `product_lines`). Always sent in full on create,
   * even empty; the update builder only includes it when the id SET changed.
   */
  rewards?: OpportunityRewardInput[]
}

/**
 * Payload for PATCH /opportunities/{id} (partial update). Every field is
 * optional (sparse diff). `lead_id` is immutable in update (BR-2, prohibited
 * server-side) and therefore not part of this shape at all.
 */
export type UpdateOpportunityPayload = Partial<
  Omit<CreateOpportunityPayload, 'lead_id' | 'supervisor_id'>
> & {
  /** Existing opportunities may keep or explicitly clear a nullable supervisor. */
  supervisor_id?: number | null
}

/**
 * BR-1: the fields a Lead's campaign can derive — only `source_id` and
 * `registry_id`. `null` = the derivation itself is null — the field then
 * stays free, per BR-2, and is NOT part of `locked_fields`. Amendment rev.3:
 * `business_function_id`/`product_category_id` are REMOVED from here — the
 * lead's derived function+category, when both exist, is exposed instead as
 * a `product_lines` row (see `OpportunityDefaults`), never locked.
 */
export interface OpportunityDefaultValues {
  referent_id: number | null
  source_id: number | null
  registry_id: number | null
  /**
   * User directive 2026-07-23: the lead's Sede operativa, inherited on
   * conversion as a PLAIN default — never part of `locked_fields`, freely
   * editable/clearable in the form.
   */
  operational_site_id: number | null
  /**
   * User directive 2026-07-27: the lead's own `notes` seed the opportunity's
   * "Note generali" — a PLAIN default like `operational_site_id` above, never
   * part of `locked_fields`.
   */
  general_notes: string | null
}

/**
 * Hydrated `{id, name|label}` projection of each `OpportunityDefaultValues`
 * entry, for the picker's edit-mode-style hydration. No `referent` (spec 0041
 * D-3): the lead's identity is its anagrafica now, not its referent.
 */
export interface OpportunityDefaultReferences {
  source: OpportunityRelationRef | null
  registry: OpportunityRelationRef | null
  /** The inherited Sede operativa's `{id,label}` ref (no `name` column server-side), for the picker's trigger label. */
  operational_site: OpportunityOperationalSiteRef | null
}

/** Response of `GET /leads/{lead}/opportunity-defaults` (spec 0040 MT-6, amendment rev.3), already unwrapped from the envelope. */
export interface OpportunityDefaults {
  lead_id: number
  /** Non-null when the lead already has an opportunity (D-2: at most one per lead) — the create page then offers to go there instead. */
  existing_opportunity_id: number | null
  values: OpportunityDefaultValues
  references: OpportunityDefaultReferences
  /** Keys of `values` whose derivation is non-null (BR-2): locked in the form, `prohibited` if sent to the server. */
  locked_fields: string[]
  /**
   * AC-102/103: 0 or 1 seed row — present only when BOTH the lead/campaign's
   * effective business function AND product category exist. EDITABLE and
   * REMOVABLE in the form, never part of `locked_fields`.
   */
  product_lines: OpportunityProductLine[]
  /**
   * User directive 2026-07-22: the lead's Operator prefills the SECOND "Gestore
   * Account" slot, G.A. 1 being materialized empty — so this is either `[]` or
   * the gap-aware `[null, operatorId]`. Editable/removable in the form, never
   * locked.
   */
  manager_slots: (number | null)[]
  /** {id,name} summaries of the filled slots, for the slot's trigger-label hydration. */
  manager_refs: OpportunityRelationRef[]
}

/**
 * The create-from-lead context threaded through the form (spec 0040 MT-6):
 * resolved once, page-side, from `OpportunityDefaults`. `lockedFields` drives
 * both the UI (`forceDisabled`) and the payload (omitted entirely, BR-1/BR-2).
 */
export interface OpportunityFromLeadContext {
  leadId: number
  values: OpportunityDefaultValues
  references: OpportunityDefaultReferences
  lockedFields: string[]
  /** AC-102/103: the lead's 0/1 seed row, editable/removable, never locked. */
  productLines: OpportunityProductLine[]
  /** Directive 2026-07-22: `[]` or `[null, operatorId]` — an empty G.A. 1 plus the lead's Operator as G.A. 2, editable/removable, never locked. */
  managerSlots: (number | null)[]
  /** {id,name} summaries of the filled slots, for the slot's trigger-label hydration. */
  managerRefs: OpportunityRelationRef[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `OpportunityForm`. */
export type OpportunityFormMode =
  | { type: 'create'; fromLead?: OpportunityFromLeadContext }
  | { type: 'edit'; opportunity: OpportunityDetailWithPermissions }
