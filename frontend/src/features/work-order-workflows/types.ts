/**
 * Work order workflow configurator types (spec 0322). Source of truth: the
 * frozen backend contract (`WorkOrderWorkflowResource`,
 * `WorkOrderWorkflowStatusResource`). Sibling of the quote configurator with
 * its own vocabulary: groups follow `Enums\WorkOrderStatus`, the pinned rows
 * are open (head) + completed, closed (tail), and each status carries a
 * `progress_percentage` instead of `requires_note`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  WorkflowStatusFormRow as SharedWorkflowStatusFormRow,
  WorkflowStatusRowPatch as SharedWorkflowStatusRowPatch,
} from '@/features/workflow-statuses/types'

/** Group values of a work order workflow status (mirror of App\Enums\WorkOrderStatus). */
export const WORK_ORDER_STATUS_GROUPS = ['open', 'in_progress', 'completed', 'closed'] as const

export type WorkOrderStatusGroupValue = (typeof WORK_ORDER_STATUS_GROUPS)[number]

/** Marks a status row as a per-set system row, or `null` for a custom one. */
export type WorkOrderWorkflowSystemKey = 'open' | 'completed' | 'closed' | null

/** Whether a `system_key` marks one of the pinned TAIL rows (`completed`/`closed`); `open` is pinned first. */
export function isTailWorkOrderSystemKey(key: WorkOrderWorkflowSystemKey): boolean {
  return key === 'completed' || key === 'closed'
}

/** One allow-listed criterion field, as returned by GET /work-order-workflows/criterion-fields. */
export interface CriterionFieldOption {
  field: string
  /** i18n key (`workOrderWorkflows.criterionFields.<field>`). */
  label: string
  source: 'native' | 'custom'
  /** for-select resource segment used to pick this field's `value_id`. */
  for_select_resource: string
  multi_valued: boolean
  inherited: boolean
}

/** One criterion of a workflow, hydrated with its resolved display label. */
export interface WorkOrderWorkflowCriterion {
  id: number
  field: string
  value_id: number
  value_label: string
}

/** One status row of a workflow's (or the global default's) set. */
export interface WorkOrderWorkflowStatusItem {
  id: number
  name: string
  description: string | null
  color: string | null
  sort_order: number
  system_key: WorkOrderWorkflowSystemKey
  group: WorkOrderStatusGroupValue
  /** Configured completion percentage (0..100); informative only in this release. */
  progress_percentage: number | null
}

/** Full workflow detail, as returned by GET/POST/PUT/PATCH /work-order-workflows(/{id}). */
export interface WorkOrderWorkflowDetail {
  id: number
  name: string
  is_active: boolean
  criteria: WorkOrderWorkflowCriterion[]
  statuses: WorkOrderWorkflowStatusItem[]
  created_at: string
  updated_at: string
}

/** A `WorkOrderWorkflowDetail` carrying the actor's authorization metadata for this instance. */
export interface WorkOrderWorkflowDetailWithPermissions extends WorkOrderWorkflowDetail {
  permissions: ResourcePermissions
}

/** One `criteria[]` entry accepted by POST/PUT/PATCH. */
export interface WorkOrderWorkflowCriterionPayload {
  field: string
  value_id: number
}

/**
 * One `statuses[]` entry of POST (create): the custom rows plus the 3 pinned
 * rows tagged with their `system_key`, whose name/color/percentage seed the
 * auto-created system rows. No `id`: nothing is persisted yet.
 */
export interface CreateWorkOrderWorkflowStatusPayload {
  name: string
  description?: string | null
  color?: string | null
  group: WorkOrderStatusGroupValue
  progress_percentage?: number | null
  system_key?: WorkOrderWorkflowSystemKey
}

/** One `statuses[]` entry of PUT/PATCH or default-statuses: `id` present = update, absent = new custom row. */
export interface UpdateWorkOrderWorkflowStatusPayload {
  id?: number
  name: string
  description?: string | null
  color?: string | null
  group: WorkOrderStatusGroupValue
  progress_percentage?: number | null
}

export interface CreateWorkOrderWorkflowPayload {
  name: string
  is_active?: boolean
  criteria: WorkOrderWorkflowCriterionPayload[]
  statuses?: CreateWorkOrderWorkflowStatusPayload[]
}

/** Sparse PATCH payload; `criteria`/`statuses` are authoritative syncs when present. */
export interface UpdateWorkOrderWorkflowPayload {
  name?: string
  is_active?: boolean
  criteria?: WorkOrderWorkflowCriterionPayload[]
  statuses?: UpdateWorkOrderWorkflowStatusPayload[]
}

/** Payload for PUT /work-order-workflows/default-statuses (the GLOBAL set). */
export interface UpdateDefaultStatusesPayload {
  statuses: UpdateWorkOrderWorkflowStatusPayload[]
}

export type WorkOrderWorkflowFormMode =
  | { type: 'create' }
  | { type: 'edit'; workOrderWorkflow: WorkOrderWorkflowDetailWithPermissions }

/** One status row as edited locally by `<WorkflowStatusesEditor>`, narrowed to the work order vocabulary. */
export interface WorkOrderStatusFormRow extends SharedWorkflowStatusFormRow {
  group: WorkOrderStatusGroupValue
  system_key: WorkOrderWorkflowSystemKey
  progress_percentage: number | null
}

export type WorkOrderStatusRowPatch = SharedWorkflowStatusRowPatch<WorkOrderStatusFormRow>
