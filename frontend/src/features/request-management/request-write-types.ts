/**
 * Request-management ("Gestione Richieste") WRITE shapes: the bodies its four
 * endpoints accept (create, work-panel update, bulk assign, transfer) and the
 * client blocks they share. Split out of `types.ts` (spec 0097) once the read
 * model alone filled that file: one module, two directions, one file each —
 * this one depends on the read model, never the other way round.
 */

import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { QuoteLineInput } from '@/features/quotes/types'
import type { RequestClientIdentity } from '@/features/request-management/types'

/**
 * Body of POST /request-management/transfer (spec 0079, frozen contract):
 * the same row/selection ids the bulk-assign endpoint takes, but the mode is
 * always `single` (never sent on the wire — the dialog locks it) and
 * `operator_id` is always required.
 */
export interface TransferRequestsPayload {
  request_ids: number[]
  operational_site_id: number
  operator_id: number
}

/** Response of the same endpoint: how many requests were actually written. */
export interface TransferRequestsResult {
  transferred: number
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
 * Payload for `PATCH /api/request-management/{quote}` (sparse diff): only the
 * sent keys change. `client_contacts`, when sent, is AUTHORITATIVE (a removed
 * row is deleted); `client_address` is a single create-or-update row and
 * never deletes the client's other addresses. `products_of_interest` is NOT
 * part of this shape (spec 0086 AC-022): the panel no longer writes it.
 */
export interface UpdateRequestWorkPayload {
  next_callback_at?: string | null
  /**
   * "Funzione aziendale" + "categoria prodotto" (user directive 2026-07-31),
   * AUTHORITATIVE when sent: the collection is fully replaced, and it may
   * never be cleared (`min:1` server-side).
   */
  product_lines?: RequestProductLinePayload[]
  /**
   * "Linee dell'offerta" (user directive 2026-08-07), AUTHORITATIVE when
   * sent: the REVENUE set is fully replaced (an omitted persisted row is
   * deleted), and omitting the key leaves it untouched. `commissions` are
   * NEVER part of a row here — the endpoint prohibits them and the server
   * preserves what the Offerte form configured.
   */
  offer_lines?: QuoteLineInput[]
  /** Attribution (user directive 2026-07-22). */
  source_id?: number | null
  reporter_id?: number | null
  /**
   * Spec 0097 rev-2 D-9: the Offerta's Supervisore, written by this module
   * again (user directive 2026-09-02). Travels like every other attribution
   * key — on its own, only when it changed — and never alongside an implied
   * team edit: it is unrelated to `manager_slots` (AC-014).
   */
  supervisor_id?: number | null
  /**
   * Spec 0097 D-1/D-5: the Offerta's whole team, ordered and gap-aware
   * (index+1 = G.A. n, `null` = an intentionally empty slot). AUTHORITATIVE
   * when sent — the positional set is synced as-is, the operator being
   * whoever fills `OPERATOR_MANAGER_POSITION`. Replaces the panel's old
   * `operator_id` key, which the endpoint now PROHIBITS alongside this one
   * (it stays the private channel of the grid cell, the bulk assign and the
   * transfer, none of which go through this payload).
   */
  manager_slots?: (number | null)[]
  /** Spec 0056: facoltativa, sent on its own like every other attribution field. */
  operational_site_id?: number | null
  client_identity?: RequestClientIdentityPayload
  client_contacts?: RequestClientContactPayload[]
  client_address?: RequestClientAddressPayload
  /** Spec 0059 D-3: reward assignments for the reporter, full-replace sync when sent. */
  rewards?: RequestRewardInput[]
  /**
   * "Informazioni aggiuntive" (user directive 2026-08-07): sparse per code —
   * an omitted code keeps its persisted value server-side.
   */
  attribute_values?: Record<string, CustomFieldValue>
  /**
   * "Stato di lavorazione" (user directive 2026-08-07). `note` travels only
   * with a transition whose target `requires_note`.
   */
  quote_workflow_status_id?: number
  note?: string
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
 * from `features/opportunities` (module-decoupling reason, same as
 * `RequestProductLine`).
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
  /**
   * "Linee dell'offerta" (user directive 2026-08-07): the created Offerta's
   * own REVENUE rows, sent only when at least one is filled in — a request
   * opened before the first call still has none (spec 0086 AC-028). Unlike
   * the picker above, an off-category product WIDENS the classification
   * (OpportunityProductLineCoverage) instead of being refused.
   */
  offer_lines?: QuoteLineInput[]
  /** Initial attribution (Fonte/Segnalatore), independent of the anagrafica XOR; `null` leaves the slot empty. */
  source_id?: number | null
  reporter_id?: number | null
  /**
   * Spec 0097 rev-2 D-9: the created Offerta's Supervisore. Sent only when
   * picked, like `operational_site_id` below: on create there is no persisted
   * value a null could clear, and an actor who may not write the field would
   * get a 422 for a key carrying no intent.
   */
  supervisor_id?: number
  /**
   * Spec 0097 D-1: the created Offerta's team, same ordered gap-aware shape as
   * the update payload. Sent only by an actor holding
   * `request-management.assignOperator`, and only once a slot is actually
   * filled: omitted (or with an empty `OPERATOR_MANAGER_POSITION`), the server
   * puts the connected actor on that slot — the same default as before.
   */
  manager_slots?: (number | null)[]
  /** Sede operativa (spec 0056), sent only when picked: on create there is no persisted value a null could clear. */
  operational_site_id?: number
  /** Spec 0059: reward assignments for the reporter, sent only when at least one is picked. */
  rewards?: RequestRewardInput[]
  /**
   * The operative fields the work panel edits, available at creation too
   * (user directive 2026-07-31, "la create il piu' simile possibile al
   * pannello"). All optional and, like the two blocks above, sent only when
   * they carry something: on create there is no persisted value a null could
   * clear, so an empty key would be pure noise on the wire.
   */
  /** `"Y-m-d\TH:i"` local format, same shape the panel PATCHes. */
  next_callback_at?: string
  general_notes?: string
  /**
   * "Informazioni aggiuntive" (user directive 2026-08-07): written on the
   * Offerta the creation opens, validated against the set the submitted
   * product lines resolve. No working-status key here — the create form does
   * not offer one, the server assigns the `open` row (spec 0083 AC-020).
   */
  attribute_values?: Record<string, CustomFieldValue>
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
 * Body of POST /request-management/assign-manager-ga1 (spec 0104): the bulk
 * GA1 assignment, the Sede-less sibling of the payload above — no
 * `operational_site_id` and no `mode`, because only the GA2 Operatore slot is
 * bound to a Sede. `manager_ga1_id` is always sent and `null` CLEARS the slot
 * on the whole selection (D-2).
 */
export interface AssignRequestManagerGa1Payload {
  request_ids: number[]
  manager_ga1_id: number | null
}

/** Response of the same endpoint: how many requests the write actually reached. */
export interface AssignRequestManagerGa1Result {
  assigned: number
}
