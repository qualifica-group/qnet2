/**
 * Opportunity workflow configurator types (spec 0047, Lane C). Source of
 * truth: the frozen backend contract (`OpportunityWorkflowResource`,
 * `OpportunityWorkflowStatusResource`, `CriterionFieldRegistry`). A workflow
 * is a NEW, distinct dimension from `opportunity-statuses` (the sales
 * pipeline) — it is not modeled on that feature's types.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Fixed group values a workflow status row carries — DEDICATED to opportunity
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

/** Marks a workflow-status row as one of the four per-set pinned rows (`open`/`validated`/`closed_won`/`closed_lost`), or `null` for a custom row. */
export type WorkflowStatusSystemKey = 'open' | 'validated' | 'closed_won' | 'closed_lost' | null

/**
 * Whether a `system_key` marks one of the pinned TAIL rows (`validated`/
 * `closed_won`/`closed_lost`), all pinned last after every custom row — the
 * anchor a newly-added custom row is inserted before. `open` is pinned first
 * and is NOT part of the tail.
 */
export function isTailWorkflowSystemKey(key: WorkflowStatusSystemKey): boolean {
  return key === 'validated' || key === 'closed_won' || key === 'closed_lost'
}

/** One allow-listed criterion field, as returned by GET /opportunity-workflows/criterion-fields (AC-022). */
export interface CriterionFieldOption {
  field: string
  /** i18n key (e.g. "opportunityWorkflows.criterionFields.state_id"), not a display string. */
  label: string
  /** for-select resource segment used to pick this field's `value_id`. */
  for_select_resource: string
  multi_valued: boolean
}

/** One criterion of a workflow, hydrated with its resolved display label. */
export interface OpportunityWorkflowCriterion {
  id: number
  field: string
  value_id: number
  value_label: string
}

/** One status row of a workflow's (or the global default's) set. */
export interface OpportunityWorkflowStatusItem {
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

/** Full workflow detail, as returned by GET/POST/PUT/PATCH /opportunity-workflows(/{id}). */
export interface OpportunityWorkflowDetail {
  id: number
  name: string
  is_active: boolean
  criteria: OpportunityWorkflowCriterion[]
  statuses: OpportunityWorkflowStatusItem[]
  created_at: string
  updated_at: string
}

/** An `OpportunityWorkflowDetail` carrying the actor's authorization metadata for this instance (spec 0004). */
export interface OpportunityWorkflowDetailWithPermissions extends OpportunityWorkflowDetail {
  permissions: ResourcePermissions
}

/** One `criteria[]` entry accepted by POST/PUT/PATCH — `value_id` is always resolved before submit. */
export interface CreateOpportunityWorkflowCriterionPayload {
  field: string
  value_id: number
}

/**
 * One `statuses[]` entry accepted by POST (create): the intermediate custom
 * rows (`system_key` null/absent) plus the 3 pinned rows tagged
 * `system_key: 'open'|'closed_won'|'closed_lost'`, whose name/color seed those
 * auto-created system rows (AC-004). No `id` — nothing is persisted yet.
 */
export interface CreateOpportunityWorkflowStatusPayload {
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
 * explicitly.
 */
export interface UpdateOpportunityWorkflowStatusPayload extends CreateOpportunityWorkflowStatusPayload {
  id?: number
}

/** Payload for POST /opportunity-workflows. */
export interface CreateOpportunityWorkflowPayload {
  name: string
  is_active?: boolean
  criteria: CreateOpportunityWorkflowCriterionPayload[]
  statuses?: CreateOpportunityWorkflowStatusPayload[]
}

/** Payload for PUT/PATCH /opportunity-workflows/{id} (sparse per-field; `criteria`/`statuses` are authoritative syncs when present). */
export interface UpdateOpportunityWorkflowPayload {
  name?: string
  is_active?: boolean
  criteria?: CreateOpportunityWorkflowCriterionPayload[]
  statuses?: UpdateOpportunityWorkflowStatusPayload[]
}

/** Payload for PUT /opportunity-workflows/default-statuses (the GLOBAL set; always a full `statuses` array). */
export interface UpdateDefaultStatusesPayload {
  statuses: UpdateOpportunityWorkflowStatusPayload[]
}

/** Discriminated form mode shared by the form hook/meta-resolver and `OpportunityWorkflowForm`. */
export type OpportunityWorkflowFormMode =
  | { type: 'create' }
  | { type: 'edit'; opportunityWorkflow: OpportunityWorkflowDetailWithPermissions }

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
