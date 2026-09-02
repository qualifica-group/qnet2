/**
 * Request-management ("Gestione Richieste") work-panel types. The record IS
 * an Offerta (Quote), exposed through a dedicated operational endpoint (spec
 * 0086 frozen `data_contract`, superseding spec 0049's Opportunity-based
 * one). Mirrors `RequestManagementResource` 1:1 — do not add fields the
 * backend doesn't send.
 */

import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { Address, GeoRef, Gender, OwnerRef, PersonalDataType } from '@/features/personal-data/types'
import type { OpportunityStatusSummary } from '@/features/opportunities/types'
import type {
  ApplicableAttributeSummary,
  QuoteLine,
  QuoteWorkflowStatusRef,
} from '@/features/quotes/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** Table/stats domain key of this module, shared by the table adapter. */
export const REQUEST_MANAGEMENT_DOMAIN = 'request-management'

/** Position-keyed G.A. label overrides (spec 0080), string keys "1".."4" — the wire shape of `manager_labels`. */
export type ManagerLabels = Record<string, string>

/**
 * The team slot that IS the "Operatore" (spec 0097 D-1): 1-based pivot
 * position, mirrors `ManagerPositions::OPERATOR` server-side, which projects it
 * onto `quotes.operator_id`. The only slot bound to the Sede operativa (D-4).
 */
export const OPERATOR_MANAGER_POSITION = 2

/** A team member of the request's Offerta: the pivot `{id, name, position}` triple, mirrors `QuoteManagerRef`. */
export interface RequestManagerRef {
  id: number
  name: string
  position: number
}

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

/** A business-function + product-category pair of one of the opportunity's rows. */
export interface RequestProductLine {
  id: number
  business_function: RequestRelationRef
  product_category: RequestRelationRef
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
 * The work panel returned by `GET /api/request-management/{quote}` (envelope
 * `data`). Matches `RequestManagementResource`.
 */
export interface RequestWorkPanel {
  /** The Offerta (Quote) id — this module's own record id since spec 0086. */
  id: number
  /**
   * Spec 0086 D-9: the underlying Opportunity's id, the identifier of the
   * COLLABORATIVE record — documents, notes, activity history and field
   * change requests stay anchored to the Opportunity (`Quote` carries none of
   * `HasAttachments`/`HasNotes`/`HasFieldChangeRequests`), so every one of
   * those surfaces reads this field, never `id`.
   */
  opportunity_id: number
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
  /**
   * Spec 0097 rev-2 D-9: the Offerta's Supervisore, editable from the panel's
   * team section again (user directive 2026-09-02, which reverses spec 0087
   * D-13/INV-5). It is the commission-recipient role
   * (`CommissionRecipientRole::Supervisor`), a plain scalar on `quotes`, and
   * it is INDEPENDENT of `operator_id`/the team's slot 2 (AC-014): writing
   * one never touches the other.
   */
  supervisor_id: number | null
  supervisor: RequestRelationRef | null
  /**
   * The GA2 "Operatore": the manager at pivot position 2, not a column of its
   * own. Since spec 0097 the two are READ-ONLY here — the grid cell, the
   * notifications and the transfer still key on them, but the panel's form
   * writes the whole team through `manager_slots` instead.
   */
  operator_id: number | null
  operator: RequestRelationRef | null
  /**
   * Spec 0097: the Offerta's own team, ordered by pivot position — the same
   * `managers` shape `QuoteResource` exposes. What the panel's `manager_slots`
   * editor hydrates from. Optional for the same fixture-compatibility reason
   * as `rewards` below — treat a missing key the same as `[]`.
   */
  managers?: RequestManagerRef[]
  /** Spec 0056: the operational site, facoltativa, editable from the attribution section like Fonte/Segnalatore/Operatore. */
  operational_site_id: number | null
  operational_site: RequestOperationalSiteRef | null
  /**
   * Spec 0079: system flags, never writable (no form/inline-editor/endpoint
   * exposes them). `true` once the request has EVER been transferred, even if
   * `transferred_from` later turns `null` (its origin Sede deleted,
   * `nullOnDelete`) — the two answer different questions and are not derived
   * from one another.
   */
  is_transferred: boolean
  transferred_from: RequestOperationalSiteRef | null
  /** Spec 0082: the COMPUTED status of the request's opportunity, read-only. */
  status: OpportunityStatusSummary
  product_lines: RequestProductLine[]
  /**
   * The offer's own REVENUE lines (spec 0086 D-7); `[]` when the offer has
   * none yet. Replaces `products_of_interest`, which this module no longer
   * exposes (AC-021/AC-022) — but unlike it, these rows ARE editable from the
   * panel since the user directive 2026-08-07, so the projection is the
   * Offerte module's own `QuoteLine` verbatim: the row editor is the same
   * component and reads the same shape.
   */
  offer_lines: QuoteLine[]
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
  /** Next follow-up call the operator scheduled, `"Y-m-d\TH:i"` local format or null (spec 0052 D-1/D-5). */
  next_callback_at: string | null
  /**
   * "Informazioni aggiuntive" (user directive 2026-08-07): the Offerta's own
   * dynamic values map, its applicable descriptors and their merged layout —
   * the same three blocks `QuoteResource` exposes, rendered by the same
   * `QuoteDynamicFieldsSection`. The applicable set is resolved from the
   * request's product lines UNIONED with its offer lines (backend D-1), since
   * a request is born with no offer line at all.
   */
  attribute_values: Record<string, CustomFieldValue>
  applicable_attributes: ApplicableAttributeSummary[]
  attribute_layout: LayoutBlob | null
  /**
   * "Stato di lavorazione" (user directive 2026-08-07): the Offerta's own
   * operational status (spec 0083). `quote_workflow_statuses` is the full set
   * the server resolved for THIS request — the only rows the panel's select
   * may offer.
   */
  quote_workflow_status_id: number | null
  quote_workflow_status: QuoteWorkflowStatusRef | null
  quote_workflow_statuses: QuoteWorkflowStatusRef[]
  context: RequestWorkContext
  /**
   * Spec 0059 D-3: reward assignments belonging to the reporter, ordered by
   * `reward_type.name`. Optional for the same fixture-compatibility reason
   * as `operational_site` elsewhere in this resource — treat a missing key
   * the same as `[]`.
   */
  rewards?: RewardAssignmentRef[]
  /**
   * Spec 0080: G.A. labels resolved from this request's own product-line
   * categories, additive. `{}` when not resolvable. Optional for the same
   * fixture-compatibility reason as `rewards` above — treat a missing key the
   * same as `{}`.
   */
  manager_labels?: ManagerLabels
}

/**
 * A `RequestWorkPanel` carrying the actor's authorization metadata for this
 * instance, as returned by both GET and PATCH `/api/request-management/{id}`.
 */
export interface RequestWorkPanelWithPermissions extends RequestWorkPanel {
  permissions: ResourcePermissions
}

/**
 * Response of `POST /api/request-management/form-context`: what the CREATE
 * form must render before anything is persisted, for the product-line
 * categories picked so far. Byte-for-byte the two blocks the work panel gets
 * already resolved, so both screens render the identical section.
 */
export interface RequestFormContext {
  applicable_attributes: ApplicableAttributeSummary[]
  attribute_layout: LayoutBlob | null
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
