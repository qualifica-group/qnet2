/**
 * Request-management ("Gestione Richieste") work-panel types. NOT a new
 * entity: the record IS an Opportunity, exposed through a dedicated
 * operational endpoint (spec 0049 frozen `data_contract`). Mirrors
 * `RequestManagementResource` 1:1 — do not add fields the backend doesn't
 * send.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { Address, GeoRef, Gender, OwnerRef, PersonalDataType } from '@/features/personal-data/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** Table/stats domain key of this module, shared by the table adapter. */
export const REQUEST_MANAGEMENT_DOMAIN = 'request-management'

/** A hydrated `{id, name}` relation projection (registry/referent/commercial). */
export interface RequestRelationRef {
  id: number
  name: string
}

/**
 * Spec 0056: the linked operational site's identity, as exposed by
 * `RequestManagementResource.operational_site`. `operational_sites` has no
 * `name` column: the identity is a server-composed "{line1} - {city}" label
 * (mirrors `OpportunityOperationalSiteRef`, kept local for the same
 * module-decoupling reason).
 */
export interface RequestOperationalSiteRef {
  id: number
  label: string
}

/** The linked opportunity status's identity, read-only in this module. */
export interface RequestOpportunityStatusRef {
  id: number
  name: string
  color: string | null
}

/**
 * A resolved working-state row (spec 0047), identical shape to
 * `OpportunityWorkflowStatusRef` minus `group` (not part of this contract).
 */
export interface RequestWorkflowStatusRef {
  id: number
  name: string
  /** Free-text explanation of the status, shown under the option in the working-status select. */
  description: string | null
  color: string | null
  system_key: string | null
  /** Marks the status as one requiring an explanatory note (configuration only). */
  requires_note: boolean
}

/** A business-function + product-category pair of one of the opportunity's rows. */
export interface RequestProductLine {
  id: number
  business_function: RequestRelationRef
  product_category: RequestRelationRef
}

/**
 * A product the operator recorded as "di interesse" for this request (user
 * directive 2026-07-22), with its own category so the panel can show which
 * product line it belongs to.
 */
export interface RequestProductOfInterest {
  id: number
  name: string
  product_category: RequestRelationRef | null
}

/** A single contact channel (ContactResource), as exposed to this module. */
export interface RequestContact {
  id: number
  type: string
  label: string | null
  value: string
  is_primary: boolean
}

/**
 * The PersonalData CARD ref backing a contacts block, already resolved by the
 * backend (Registry/Referent are not a valid `personable_type` for a
 * standalone card fetch) — fed straight into `ContactsManager`'s
 * `persistence` prop. `null` when the opportunity has no linked owner of
 * this kind yet (no card to persist against).
 */
export type RequestContactsOwnerRef = OwnerRef & { type: 'personal_data' }

/**
 * The client's identity fields, the PersonalData card of the linked registry:
 * who the client is (individual vs company) and the fiscal identifiers
 * (tax code, VAT number, SDI). `null` when the client has no card yet — the
 * panel then hides the block, there being no write path for it.
 */
export interface RequestClientIdentity {
  id: number
  type: PersonalDataType
  first_name: string | null
  last_name: string | null
  company_name: string | null
  tax_code: string | null
  vat_number: string | null
  sdi_code: string | null
  birth_date: string | null
  birth_city_id: number | null
  /** Hydrated comune of birth, read-only label for the select (never sent back). */
  birth_city: GeoRef | null
  residence_city_id: number | null
  /** Hydrated comune of residence, read-only label (never sent back). */
  residence_city: GeoRef | null
  gender: Gender | null
}

/** A contacts block: the owner to persist against, plus its current contacts. */
export interface RequestContactsBlock {
  owner: RequestContactsOwnerRef | null
  items: RequestContact[]
}

/** A single selectable option of an enum/relation-typed attribute. */
export interface ApplicableAttributeOption {
  value: string
  label: string
  color: string | null
}

/**
 * A dynamic field derived from the Product Category's effective Attributes
 * (union, dedup by `code`, across all of the opportunity's product lines).
 */
export interface ApplicableAttribute {
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
  options: ApplicableAttributeOption[]
}

/** Read-only commercial context surfaced alongside the work panel. */
export interface RequestWorkContext {
  estimated_value: string | number | null
  expected_close_date: string | null
  success_probability: number | null
  /**
   * "Note generali" (user directive 2026-07-27): the opportunity's free-text
   * notes, inherited from the originating lead. READ-ONLY in this module —
   * highlighted at the top of the side column because operators rely on it.
   * Optional for the same fixture-compatibility reason as `rewards` below;
   * treat a missing key the same as `null`.
   */
  general_notes?: string | null
}

/**
 * The work panel returned by `GET /api/request-management/{opportunity}`
 * (envelope `data`). Matches `RequestManagementResource`.
 */
export interface RequestWorkPanel {
  id: number
  name: string
  registry: RequestRelationRef | null
  referent: RequestRelationRef | null
  commercial: RequestRelationRef | null
  /** "Fonte" (user directive 2026-07-22): editable from the panel's attribution section. */
  source_id: number | null
  source: RequestRelationRef | null
  /** "Segnalatore": a Referent, same picker as the opportunities form. */
  reporter_id: number | null
  reporter: RequestRelationRef | null
  /** The GA2 "Operatore": the manager at pivot position 2, not a column of its own. */
  operator_id: number | null
  operator: RequestRelationRef | null
  /** Spec 0056: the operational site, facoltativa, editable from the attribution section like Fonte/Segnalatore/Operatore. */
  operational_site_id: number | null
  operational_site: RequestOperationalSiteRef | null
  /** Sales-pipeline status: read-only in this module. */
  opportunity_status: RequestOpportunityStatusRef | null
  workflow_status: RequestWorkflowStatusRef | null
  /** The resolved set the workflow-status select is limited to. */
  workflow_statuses: RequestWorkflowStatusRef[]
  product_lines: RequestProductLine[]
  /** Products of interest recorded for this request; `[]` when none (user directive 2026-07-22). */
  products_of_interest: RequestProductOfInterest[]
  /** The client's card identity, `null` when the client has no card yet. */
  client_identity: RequestClientIdentity | null
  client_contacts: RequestContactsBlock
  /**
   * The client's PRIMARY address (AddressResource), the single row the
   * anagraphic section edits inline. `null` when the client has no card or no
   * address yet — the section then starts blank and a save creates the first.
   */
  client_address: Address | null
  referent_contacts: RequestContactsBlock
  /** Union, dedup by `code`, of the effective attributes of every product line. */
  applicable_attributes: ApplicableAttribute[]
  /** Current values keyed by attribute `code`; `{}` when none. */
  attribute_values: Record<string, unknown>
  /**
   * The merged, multi-category resolved attribute layout for the panel's
   * form mode, additive (spec 0062). `null`/absent falls back to the flat
   * rendering of `applicable_attributes` (AC-007) — optional for the same
   * fixture-compatibility convention as `rewards` below.
   */
  attribute_layout?: LayoutBlob | null
  /** Next follow-up call the operator scheduled, `"Y-m-d\TH:i"` local format or null (spec 0052 D-1/D-5). */
  next_callback_at: string | null
  context: RequestWorkContext
  /**
   * Spec 0059 D-3: reward assignments belonging to the reporter, ordered by
   * `reward_type.name`. Optional for the same fixture-compatibility reason
   * as `operational_site`/`workflow_status` elsewhere in this resource —
   * treat a missing key the same as `[]`.
   */
  rewards?: RewardAssignmentRef[]
}

/**
 * A `RequestWorkPanel` carrying the actor's authorization metadata for this
 * instance, as returned by both GET and PATCH `/api/request-management/{id}`.
 */
export interface RequestWorkPanelWithPermissions extends RequestWorkPanel {
  permissions: ResourcePermissions
}

/**
 * The client's identity write set: a FULL replace of the card's identity
 * fields (no `id` — the server resolves the card from the request's client).
 * Saving it also re-derives the client's display name server-side.
 */
export type RequestClientIdentityPayload = Omit<
  RequestClientIdentity,
  'id' | 'birth_city' | 'residence_city'
>

/** One contact row of the `client_contacts` write set (`id` present = update). */
export interface RequestClientContactPayload {
  id?: number
  type: string
  value: string
  label: string | null
  is_primary: boolean
}

/**
 * The single client address row (`id` present = update that row). Carries only
 * the fields the panel actually edits: the primary flag, site type and
 * coordinates are preserved server-side (RequestClientProfileWriter).
 */
export interface RequestClientAddressPayload {
  id?: number
  line1: string
  line2: string | null
  postal_code: string | null
  city_id: number | null
  province_id: number | null
  state_id: number | null
  country_id: number | null
}

/**
 * Payload for `PATCH /api/request-management/{opportunity}` (sparse diff):
 * only the sent keys change. `attribute_values`, when sent, replaces the
 * entire map (keys not included are left untouched server-side).
 * `client_contacts`, when sent, is AUTHORITATIVE (a removed row is deleted);
 * `client_address` is a single create-or-update row and never deletes the
 * client's other addresses.
 */
export interface UpdateRequestWorkPayload {
  opportunity_workflow_status_id?: number | null
  note?: string
  attribute_values?: Record<string, unknown>
  next_callback_at?: string | null
  /**
   * Product ids, AUTHORITATIVE when sent (`[]` clears the collection). A
   * product outside the opportunity's product-line categories is accepted:
   * the server then ADDS the matching business function / product category
   * row, which is what the picker's unlock dialog warns about.
   */
  products_of_interest?: number[]
  /**
   * "Funzione aziendale" + "categoria prodotto" (user directive 2026-07-31),
   * AUTHORITATIVE when sent: the collection is fully replaced, and it may
   * never be cleared (`min:1` server-side). Dropping a category whose
   * products of interest are still selected is rejected 422 — send the new
   * `products_of_interest` in the same PATCH.
   */
  product_lines?: RequestProductLinePayload[]
  /**
   * Attribution (user directive 2026-07-22). `operator_id` addresses the GA2
   * pivot slot only: the other manager positions are left untouched, and
   * `null` empties the slot.
   */
  source_id?: number | null
  reporter_id?: number | null
  operator_id?: number | null
  /** Spec 0056: facoltativa, sent on its own like every other attribution field. */
  operational_site_id?: number | null
  client_identity?: RequestClientIdentityPayload
  client_contacts?: RequestClientContactPayload[]
  client_address?: RequestClientAddressPayload
  /** Spec 0059 D-3: reward assignments for the reporter, full-replace sync when sent. */
  rewards?: RequestRewardInput[]
}

/**
 * One `product_lines` row on the wire, shared by the create payload (D-3) and
 * the work panel's own update (user directive 2026-07-31): both ids are
 * mandatory there, unlike the form's in-progress rows.
 */
export interface RequestProductLinePayload {
  business_function_id: number
  product_category_id: number
}

/**
 * One `rewards` row of the update payload (spec 0059 §4): only the type id
 * travels — the beneficiary (the reporter) and `assigned_at` are derived
 * server-side by `RewardAssignmentWriter`. Kept LOCAL rather than imported
 * from `features/opportunities` (same module-decoupling reason as
 * `ApplicableAttributeSummary`/`RequestProductLine`).
 */
export interface RequestRewardInput {
  reward_type_id: number
}

/**
 * Body of POST /request-management (spec 0057, frozen contract): exactly one
 * anagrafica source — `registry_id` XOR the `client_*` blocks (D-2, the
 * server rejects both together and neither) — plus the mandatory
 * `product_lines` (D-3). Reuses the same `RequestClient*Payload` shapes the
 * update endpoint already defines: a create-time contact/address never
 * carries an `id`, but the type stays optional there so both endpoints share
 * one definition.
 */
export interface CreateRequestPayload {
  registry_id?: number
  client_identity?: RequestClientIdentityPayload
  client_contacts?: RequestClientContactPayload[]
  client_address?: RequestClientAddressPayload
  product_lines: RequestProductLinePayload[]
  /**
   * "Prodotti di interesse" (user directive 2026-07-31): optional at creation,
   * sent only when at least one is picked. Every product must belong to one of
   * `product_lines`' categories — the server refuses the mismatch instead of
   * covering it with an extra product line.
   */
  products_of_interest?: number[]
  /** Initial attribution (Fonte/Segnalatore), independent of the anagrafica XOR; `null` leaves the slot empty. */
  source_id?: number | null
  reporter_id?: number | null
  /** GA2 "Operatore", sent only by an actor holding `request-management.assignOperator`. */
  operator_id?: number
  /** Sede operativa (spec 0056), sent only when picked: on create there is no persisted value a null could clear. */
  operational_site_id?: number
  /** Spec 0059: reward assignments for the reporter, sent only when at least one is picked. */
  rewards?: RequestRewardInput[]
  /**
   * The five operative fields the work panel edits, available at creation too
   * (user directive 2026-07-31, "la create il piu' simile possibile al
   * pannello"). All optional and, like the two blocks above, sent only when
   * they carry something: on create there is no persisted value a null could
   * clear, so an empty key would be pure noise on the wire.
   *
   * The working status must belong to the set resolved for the submitted
   * `source_id`/`product_lines` — the set
   * `POST /request-management/form-context` previewed; `note` is mandatory
   * server-side when that status is flagged `requires_note` (spec 0054 D-5).
   */
  opportunity_workflow_status_id?: number
  note?: string
  /** `"Y-m-d\TH:i"` local format, same shape the panel PATCHes. */
  next_callback_at?: string
  general_notes?: string
  /** Dynamic values keyed by attribute `code`, validated against the applicable set of the created request. */
  attribute_values?: Record<string, unknown>
}

/**
 * Body of POST /request-management/form-context (user directive 2026-07-31):
 * the criteria the create form has collected so far. Half-filled product lines
 * are dropped client-side (they scope nothing) exactly as the server does.
 */
export interface RequestFormContextPayload {
  source_id: number | null
  product_lines: RequestProductLinePayload[]
}

/**
 * Response of the same endpoint: the three blocks the create form needs before
 * anything is persisted, resolved from the criteria above. Same keys and same
 * per-item shapes as the work panel's own — the two forms render the identical
 * sections from the identical types.
 */
export interface RequestFormContext {
  applicable_attributes: ApplicableAttribute[]
  attribute_layout: LayoutBlob | null
  workflow_statuses: RequestWorkflowStatusRef[]
}

/**
 * Body of POST /request-management/assign-operators (user directive
 * 2026-07-23): the same two-mode contract the shared AssignOperatorsDialog
 * collects, keyed on requests instead of leads. `operator_id` is sent only in
 * `single` mode.
 */
export interface AssignRequestOperatorsPayload {
  request_ids: number[]
  operational_site_id: number
  mode: 'single' | 'balanced'
  operator_id?: number
}

/** Response of the same endpoint: how many requests were actually written. */
export interface AssignRequestOperatorsResult {
  assigned: number
}

/**
 * One Product Category tab (spec 0064), as returned by
 * `GET /api/request-management/product-categories`: only categories with at
 * least one request in the actor's own scope, ordered by `name`.
 * `requests_count` may sum to more than the "Tutte" total, since a request
 * with several product lines counts under every one of its categories (D-2).
 */
export interface RequestManagementProductCategory {
  id: number
  name: string
  requests_count: number
}
