import axios from 'axios'

/** Server-side field names mapped back onto the form for 422 handling. */
export const SERVER_ERROR_FIELDS = [
  'title',
  'description',
  'is_private',
  'is_completed',
  'registry_id',
  'referent_id',
  'parent_task_id',
  'task_type_id',
  'task_priority_id',
  'task_importance_id',
  'task_category_id',
  'opportunity_id',
  'work_order_id',
  'work_order_stage_id',
  'lead_id',
  'requester_id',
  'start_date',
  'end_date',
  'start_time',
  'end_time',
  'estimated_minutes',
  'requires_closure_feedback',
  'requires_validation',
  'assignee_ids',
  'watcher_ids',
  'recurrence.frequency',
  'recurrence.interval',
  'recurrence.weekdays',
  'recurrence.month_day',
  'recurrence.ends',
  'recurrence.ends_on',
  'recurrence.occurrence_count',
] as const

/**
 * AC-022: a 422 on either of these has nowhere to land on this form —
 * `closure_feedback` left it entirely (spec 0121 D-7), and `task_status_id`'s
 * refusal here is `TaskValidationRequirementGuard` (D-5), a workflow rule the
 * status picker cannot express, not a picker-level validation. `recurrence`
 * itself joins them for the same reason (spec 0120): the bare key 422s either
 * on a frozen Task (D-13, `TaskWriteLock`) or a missing `end_date` (D-1), and
 * neither is a control the "Ricorrenza" section owns — the master switch is
 * not "the end date". All three surface as a toast with the server's own
 * message instead of a field error.
 */
export const TOAST_ONLY_SERVER_ERROR_FIELDS = ['closure_feedback', 'task_status_id', 'recurrence'] as const

/** The first message the server attached to `field` in a 422 response, or `null`. */
export function serverFieldMessage(error: unknown, field: string): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  return errors?.[field]?.[0] ?? null
}

/**
 * Spec 0146 D-4/AC-015: a store/update 409 on this endpoint has exactly one
 * documented cause — the picked fase is closed. Unlike a 422 it carries no
 * per-field `errors` map, only the envelope's own `message` (backend.md
 * `{success, message}`), so it is surfaced verbatim as the "Fase" field's
 * error rather than extracted by key.
 */
export function workOrderStageConflictMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 409) {
    return null
  }
  const message = error.response.data?.message as string | undefined
  return message ?? null
}
