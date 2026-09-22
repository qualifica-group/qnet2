import type {
  CreateTaskTemplateItemPayload,
  CreateTaskTemplatePayload,
  CreateTaskTemplateStagePayload,
  TaskTemplateDetail,
  TaskTemplateItemFormRow,
  TaskTemplateStageFormRow,
  UpdateTaskTemplateItemPayload,
  UpdateTaskTemplatePayload,
  UpdateTaskTemplateStagePayload,
} from '@/features/task-templates/types'
import type { TaskTemplateFormValues } from '@/features/task-templates/use-task-template-form'

/** Projects a row onto the wire shape every `items[]` entry shares, `id` aside. */
function buildItemFields(row: TaskTemplateItemFormRow): CreateTaskTemplateItemPayload {
  return {
    title: row.title,
    description: row.description,
    estimated_minutes: row.estimated_minutes,
    task_status_id: row.task_status_id,
    due_offset_days: row.due_offset_days,
    stage_key: row.stage_key,
  }
}

/** Projects a row onto the wire shape every `stages[]` entry shares, `id` aside: `id` doubles as `key` (spec 0146 D-2). */
function buildStageFields(row: TaskTemplateStageFormRow): CreateTaskTemplateStagePayload {
  return { key: row.id, name: row.name }
}

/**
 * Builds the create payload: every item row in visual order, never carrying
 * an `id` (D-1); every stage row the same way, `stages.*.key` resolving the
 * items' own `stage_key` within this SAME request (spec 0146 D-2/AC-001).
 */
export function buildCreatePayload(
  values: TaskTemplateFormValues,
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
): CreateTaskTemplatePayload {
  return {
    name: values.name,
    description: values.description,
    is_active: values.is_active,
    items: itemRows.map(buildItemFields),
    stages: stageRows.map(buildStageFields),
  }
}

/**
 * Builds the update payload: `name`/`description`/`is_active` only when they
 * actually changed from `original`, but `items`/`stages` are ALWAYS the full
 * authoritative sync (a row missing from either array is deleted
 * server-side, D-1/D-2/AC-004/AC-002/AC-005) — never a sparse diff, mirrors
 * `quote-workflow-form-payload.ts`'s `buildStatusesUpdatePayload`.
 */
export function buildUpdatePayload(
  values: TaskTemplateFormValues,
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
  original: TaskTemplateDetail,
): UpdateTaskTemplatePayload {
  const payload: UpdateTaskTemplatePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  payload.items = itemRows.map(
    (row): UpdateTaskTemplateItemPayload => ({
      id: row.itemId,
      ...buildItemFields(row),
    }),
  )
  payload.stages = stageRows.map(
    (row): UpdateTaskTemplateStagePayload => ({
      id: row.stageId,
      ...buildStageFields(row),
    }),
  )

  return payload
}
