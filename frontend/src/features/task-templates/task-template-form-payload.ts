import type {
  CreateTaskTemplateItemPayload,
  CreateTaskTemplatePayload,
  CreateTaskTemplateStagePayload,
  TaskTemplateItemFormRow,
  TaskTemplateStageFormRow,
  UpdateTaskTemplateItemPayload,
  UpdateTaskTemplatePayload,
  UpdateTaskTemplateStagePayload,
} from '@/features/task-templates/types'
import type { TaskTemplateFormValues } from '@/features/task-templates/use-task-template-form'

/**
 * Projects a row onto the wire shape every `items[]` entry shares, `id`
 * aside: `key` is this row's own local `id`, `parent_key` the owning row's
 * (spec 0172 D-1/D-2, mirrors `stage_key`'s own client-key pattern) — always
 * sent, so a plain single-level template just carries `parent_key: null`
 * throughout.
 */
function buildItemFields(row: TaskTemplateItemFormRow): CreateTaskTemplateItemPayload {
  return {
    title: row.title,
    description: row.description,
    estimated_minutes: row.estimated_minutes,
    task_status_id: row.task_status_id,
    due_offset_days: row.due_offset_days,
    stage_key: row.stage_key,
    key: row.id,
    parent_key: row.parent_key,
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
 * Builds the board's update payload (spec 0227 D-3): `items`/`stages` only,
 * ALWAYS the full authoritative sync (a row missing from either array is
 * deleted server-side, D-1/D-2/AC-004) — the header fields save on their own
 * rows, so they never ride along.
 */
export function buildBoardUpdatePayload(
  itemRows: TaskTemplateItemFormRow[],
  stageRows: TaskTemplateStageFormRow[],
): UpdateTaskTemplatePayload {
  return {
    items: itemRows.map((row): UpdateTaskTemplateItemPayload => ({ id: row.itemId, ...buildItemFields(row) })),
    stages: stageRows.map((row): UpdateTaskTemplateStagePayload => ({ id: row.stageId, ...buildStageFields(row) })),
  }
}
