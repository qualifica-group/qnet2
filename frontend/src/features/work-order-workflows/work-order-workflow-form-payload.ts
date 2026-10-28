import type {
  CreateWorkOrderWorkflowPayload,
  CreateWorkOrderWorkflowStatusPayload,
  UpdateDefaultStatusesPayload,
  UpdateWorkOrderWorkflowPayload,
  UpdateWorkOrderWorkflowStatusPayload,
  WorkOrderStatusFormRow,
  WorkOrderWorkflowCriterionPayload,
  WorkOrderWorkflowDetail,
} from '@/features/work-order-workflows/types'
import { statusRowsFromDetail } from '@/features/work-order-workflows/work-order-status-rows'
import type { WorkOrderWorkflowFormValues } from '@/features/work-order-workflows/work-order-workflow-schema'

/** One `criteria[]` row exactly as the RHF field array edits it (each id individually nullable while the row fills in). */
type CriterionFormRow = WorkOrderWorkflowFormValues['criteria'][number]

/** Drops any row still missing a `field`/`value_id` (defensive: the schema already blocks submit on them). */
function buildCriteriaPayload(rows: CriterionFormRow[]): WorkOrderWorkflowCriterionPayload[] {
  return rows
    .filter(
      (row): row is { field: string; value_id: number } =>
        row.field !== null && row.field !== '' && row.value_id !== null,
    )
    .map((row) => ({ field: row.field, value_id: row.value_id }))
}

/** CREATE `statuses[]`: every row in visual order, the pinned ones carrying their `system_key` to seed the system rows. */
function buildStatusesCreatePayload(rows: WorkOrderStatusFormRow[]): CreateWorkOrderWorkflowStatusPayload[] {
  return rows.map((row) => ({
    name: row.name,
    description: row.description,
    color: row.color,
    group: row.group,
    progress_percentage: row.progress_percentage,
    system_key: row.system_key,
  }))
}

/** UPDATE/default-statuses `statuses[]`: `id` present = update (system or custom), absent = a new custom row. */
function buildStatusesUpdatePayload(rows: WorkOrderStatusFormRow[]): UpdateWorkOrderWorkflowStatusPayload[] {
  return rows
    .filter((row) => row.statusId !== undefined || row.system_key === null)
    .map((row) => ({
      id: row.statusId,
      name: row.name,
      description: row.description,
      color: row.color,
      group: row.group,
      progress_percentage: row.progress_percentage,
    }))
}

export function buildCreatePayload(values: WorkOrderWorkflowFormValues): CreateWorkOrderWorkflowPayload {
  return {
    name: values.name,
    is_active: values.is_active,
    criteria: buildCriteriaPayload(values.criteria),
    statuses: buildStatusesCreatePayload(values.statuses),
  }
}

/** Partial PATCH carrying only what changed; `criteria`/`statuses` are authoritative syncs sent whole when changed. */
export function buildUpdatePayload(
  values: WorkOrderWorkflowFormValues,
  original: WorkOrderWorkflowDetail,
): UpdateWorkOrderWorkflowPayload {
  const payload: UpdateWorkOrderWorkflowPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  const criteria = buildCriteriaPayload(values.criteria)
  const originalCriteria = original.criteria.map(({ field, value_id }) => ({ field, value_id }))
  if (JSON.stringify(criteria) !== JSON.stringify(originalCriteria)) {
    payload.criteria = criteria
  }

  const statuses = buildStatusesUpdatePayload(values.statuses)
  if (JSON.stringify(statuses) !== JSON.stringify(buildStatusesUpdatePayload(statusRowsFromDetail(original)))) {
    payload.statuses = statuses
  }

  return payload
}

/** PUT /work-order-workflows/default-statuses payload from the shared editor's local rows. */
export function buildDefaultStatusesPayload(statusRows: WorkOrderStatusFormRow[]): UpdateDefaultStatusesPayload {
  return { statuses: buildStatusesUpdatePayload(statusRows) }
}
