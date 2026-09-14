import type {
  CreateTaskTemplateItemPayload,
  CreateTaskTemplatePayload,
  TaskTemplateDetail,
  TaskTemplateItemFormRow,
  UpdateTaskTemplateItemPayload,
  UpdateTaskTemplatePayload,
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
  }
}

/** Builds the create payload: every row in visual order, never carrying an `id` (D-1). */
export function buildCreatePayload(
  values: TaskTemplateFormValues,
  itemRows: TaskTemplateItemFormRow[],
): CreateTaskTemplatePayload {
  return {
    name: values.name,
    description: values.description,
    is_active: values.is_active,
    items: itemRows.map(buildItemFields),
  }
}

/**
 * Builds the update payload: `name`/`description`/`is_active` only when they
 * actually changed from `original`, but `items` is ALWAYS the full
 * authoritative sync (a row missing from this array is deleted server-side,
 * D-1/AC-004) — never a sparse diff, mirrors
 * `quote-workflow-form-payload.ts`'s `buildStatusesUpdatePayload`.
 */
export function buildUpdatePayload(
  values: TaskTemplateFormValues,
  itemRows: TaskTemplateItemFormRow[],
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

  return payload
}
