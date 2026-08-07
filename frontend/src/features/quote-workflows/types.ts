/**
 * Quote workflow configurator types (spec 0047, moved onto the Offerta by
 * spec 0083 D-6). Source of truth: the frozen backend contract
 * (`QuoteWorkflowResource`, `QuoteWorkflowStatusResource`,
 * `QuoteCriterionFieldRegistry`). A workflow is a NEW, distinct dimension
 * from the sales pipeline — it is not modeled on that feature's types.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Fixed group values a workflow status row carries — DEDICATED to quote
 * workflows (mirror of App\Enums\WorkflowStatusGroup), distinct from the
 * shared `StatusGroupValue` (pipeline/opportunity statuses, still
 * open/pending/closed): here the working phase runs open -> pending ->
 * `validated` (esito accertato, non ancora chiuso), then the terminal "closed"
 * phase splits into its two outcomes, `closed_won` (esito positivo) and
 * `closed_lost` (esito negativo).
 */
export const WORKFLOW_STATUS_GROUPS = ['open', 'pending', 'validated', 'closed_won', 'closed_lost'] as const

/** One of the five fixed workflow-status group values. */
export type WorkflowStatusGroupValue = (typeof WORKFLOW_STATUS_GROUPS)[number]

/**
 * Marks a workflow-status row as a per-set system row, or `null` for a custom
 * one. All three are MANDATORY: every set is created with them and none can
 * be deleted. There is no system key for the "validated" phase (user
 * directive 2026-08-07) — that is a plain `group` any custom row may carry.
 */
export type WorkflowStatusSystemKey = 'open' | 'closed_won' | 'closed_lost' | null

/**
 * Whether a `system_key` marks one of the pinned TAIL rows (`closed_won`/
 * `closed_lost`), both pinned last after every custom row — the anchor a
 * newly-added custom row is inserted before. `open` is pinned first and is
 * NOT part of the tail.
 */
export function isTailWorkflowSystemKey(key: WorkflowStatusSystemKey): boolean {
  return key === 'closed_won' || key === 'closed_lost'
}

/**
 * One allow-listed criterion field, as returned by GET
 * /quote-workflows/criterion-fields (AC-022, extended by AC-027/AC-035
 * with custom relational fields; spec 0083 AC-016 adds `inherited`).
 * `source` discriminates how `label` must be rendered: an i18n key for
 * `native`, already-readable text for `custom` (D9) — never `t()` a custom
 * label.
 */
export interface CriterionFieldOption {
  field: string
  /** i18n key for `source: 'native'`; literal display text for `source: 'custom'`. */
  label: string
  source: 'native' | 'custom'
  /** for-select resource segment used to pick this field's `value_id`. */
  for_select_resource: string
  multi_valued: boolean
  /** Spec 0083 (D-7): true when the field is resolved from the parent Opportunity rather than the Offerta itself. */
  inherited: boolean
}

/** One criterion of a workflow, hydrated with its resolved display label. */
export interface QuoteWorkflowCriterion {
  id: number
  field: string
  value_id: number
  value_label: string
  /** Same shape as `CriterionFieldOption.label`: an i18n key for `field_source: 'native'`, literal text for `'custom'`. Falls back to the raw `field` (as `native`) if the field left the allow-list (D10). */
  field_label: string
  field_source: 'native' | 'custom'
}

/** One status row of a workflow's (or the global default's) set. */
export interface QuoteWorkflowStatusItem {
  id: number
  name: string
  /** Free-text explanation of the status, shown in the editor, the status select and the table badge tooltip. */
  description: string | null
  color: string | null
  sort_order: number
  system_key: WorkflowStatusSystemKey
  group: WorkflowStatusGroupValue
  /** Marks the status as one requiring an explanatory note. Configuration only: nothing enforces the note yet. */
  requires_note: boolean
}

/** Full workflow detail, as returned by GET/POST/PUT/PATCH /quote-workflows(/{id}). */
export interface QuoteWorkflowDetail {
  id: number
  name: string
  is_active: boolean
  criteria: QuoteWorkflowCriterion[]
  statuses: QuoteWorkflowStatusItem[]
  created_at: string
  updated_at: string
}

/** A `QuoteWorkflowDetail` carrying the actor's authorization metadata for this instance (spec 0004). */
export interface QuoteWorkflowDetailWithPermissions extends QuoteWorkflowDetail {
  permissions: ResourcePermissions
}

/** One `criteria[]` entry accepted by POST/PUT/PATCH — `value_id` is always resolved before submit. */
export interface CreateQuoteWorkflowCriterionPayload {
  field: string
  value_id: number
}

/**
 * One `statuses[]` entry accepted by POST (create): the intermediate custom
 * rows (`system_key` null/absent) plus the 3 pinned rows tagged
 * `system_key: 'open'|'closed_won'|'closed_lost'`, whose name/color seed those
 * auto-created system rows (AC-004). No `id` — nothing is persisted yet.
 */
export interface CreateQuoteWorkflowStatusPayload {
  name: string
  description?: string | null
  color?: string | null
  group: WorkflowStatusGroupValue
  requires_note?: boolean
  system_key?: WorkflowStatusSystemKey
}

/**
 * One `statuses[]` entry accepted by PUT/PATCH (update) or the default-status
 * endpoint: `id` present = update an existing row (system or custom), absent
 * = a new custom row. Sort order is positional (array index), never sent
 * explicitly. No `system_key`: on update a system row is identified by its
 * persisted `id`, and no payload can claim or release a key.
 */
export interface UpdateQuoteWorkflowStatusPayload {
  id?: number
  name: string
  description?: string | null
  color?: string | null
  group: WorkflowStatusGroupValue
  requires_note?: boolean
}

/** Payload for POST /quote-workflows. */
export interface CreateQuoteWorkflowPayload {
  name: string
  is_active?: boolean
  criteria: CreateQuoteWorkflowCriterionPayload[]
  statuses?: CreateQuoteWorkflowStatusPayload[]
}

/** Payload for PUT/PATCH /quote-workflows/{id} (sparse per-field; `criteria`/`statuses` are authoritative syncs when present). */
export interface UpdateQuoteWorkflowPayload {
  name?: string
  is_active?: boolean
  criteria?: CreateQuoteWorkflowCriterionPayload[]
  statuses?: UpdateQuoteWorkflowStatusPayload[]
}

/** Payload for PUT /quote-workflows/default-statuses (the GLOBAL set; always a full `statuses` array). */
export interface UpdateDefaultStatusesPayload {
  statuses: UpdateQuoteWorkflowStatusPayload[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `QuoteWorkflowForm`. */
export type QuoteWorkflowFormMode =
  | { type: 'create' }
  | { type: 'edit'; quoteWorkflow: QuoteWorkflowDetailWithPermissions }

/**
 * One status row as edited locally by `<WorkflowStatusesEditor>` (a
 * SortableList-driven local array, not an RHF field array — mirrors
 * `StatusReorderItem`/`useStatusReorder`). `id` is the STRING identity
 * `<SortableList>` requires (mirrors every other row's stringified id);
 * `statusId` is the persisted backend id, or `undefined` for a row not yet
 * created (a freshly-added custom row, or the 3 pinned system rows in create
 * mode — the backend then persists the real open/closed_won/closed_lost rows,
 * seeded with the name/color the user typed, AC-004).
 */
export interface WorkflowStatusFormRow {
  id: string
  statusId?: number
  name: string
  description: string | null
  color: string | null
  group: WorkflowStatusGroupValue
  system_key: WorkflowStatusSystemKey
  requires_note: boolean
}

/** The row fields the editor may patch (everything but the row identity and its pinned `system_key`). */
export type WorkflowStatusRowPatch = Partial<
  Pick<WorkflowStatusFormRow, 'name' | 'description' | 'color' | 'group' | 'requires_note'>
>
