import type {
  CreateTaskTypePayload,
  TaskTypeDetail,
  UpdateTaskTypePayload,
} from '@/features/task-types/types'
import type { TaskTypeFormValues } from '@/features/task-types/use-task-type-form'

/** Maps the form's `icon` (empty string = unset) onto the backend's nullable value. */
function iconValue(icon: string): string | null {
  return icon === '' ? null : icon
}

/** Builds the create payload: every field the form owns (never `sort_order`). */
export function buildCreatePayload(values: TaskTypeFormValues): CreateTaskTypePayload {
  return {
    name: values.name,
    color: values.color,
    icon: iconValue(values.icon),
    description: values.description,
    is_active: values.is_active,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original task type. `sort_order` is NEVER
 * included: the backend 422s on its mere presence.
 */
export function buildUpdatePayload(
  values: TaskTypeFormValues,
  original: TaskTypeDetail,
): UpdateTaskTypePayload {
  const payload: UpdateTaskTypePayload = {}

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
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}
