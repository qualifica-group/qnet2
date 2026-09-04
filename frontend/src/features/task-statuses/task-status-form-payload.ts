import type {
  CreateTaskStatusPayload,
  TaskStatusDetail,
  UpdateTaskStatusPayload,
} from '@/features/task-statuses/types'
import type { TaskStatusFormValues } from '@/features/task-statuses/use-task-status-form'

/** Maps the form's `icon` (empty string = unset) onto the backend's nullable value. */
function iconValue(icon: string): string | null {
  return icon === '' ? null : icon
}

/** Builds the create payload: every field the form owns (never `sort_order`/`system_key`). */
export function buildCreatePayload(values: TaskStatusFormValues): CreateTaskStatusPayload {
  return {
    name: values.name,
    color: values.color,
    icon: iconValue(values.icon),
    description: values.description,
    group: values.group,
    is_active: values.is_active,
    completion_percentage: values.completion_percentage,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original task status. `sort_order` and `system_key` are NEVER
 * included: the backend 422s on their mere presence.
 */
export function buildUpdatePayload(
  values: TaskStatusFormValues,
  original: TaskStatusDetail,
): UpdateTaskStatusPayload {
  const payload: UpdateTaskStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }
  if (iconValue(values.icon) !== original.icon) {
    payload.icon = iconValue(values.icon)
  }
  if (values.group !== original.group) {
    payload.group = values.group
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }
  if (values.completion_percentage !== original.completion_percentage) {
    payload.completion_percentage = values.completion_percentage
  }

  return payload
}
