import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { TaskActionKey, TaskDetail } from '@/features/tasks/types'

/**
 * Client-side mirror of `App\Services\Tasks\TaskActionAvailability`
 * (spec 0116 D-1/D-8): WHEN a domain action makes sense, given the task's
 * current phase and `is_blocked`. This is an AVAILABILITY rule, NOT an
 * authorization one — mirrors `contract-lifecycle.ts` verbatim. Every caller
 * ANDs this with `task.permissions.actions.X` (the server-computed flag,
 * itself already ability AND record-role-matrix AND this same availability)
 * before rendering a button, exactly as `contract-actions-bar.tsx` does
 * `lifecycle.X && contract.permissions.actions.X`. This file is UX only: the
 * server re-asserts every one of these rules (409/422) regardless of what the
 * button shows.
 */
/**
 * `close_via_status`/`create_subtask` (spec 0123 D-5/D-9) are EXCLUDED here on
 * purpose, same reasoning as `complete_to_validation`'s own exclusion from
 * `TaskActionKey`'s doc comment: they are not phase-based buttons this file
 * mirrors, callers read `task.permissions.actions.X` for them directly.
 */
export type TaskActionAvailabilityFlags = Record<
  Exclude<TaskActionKey, 'close_via_status' | 'create_subtask'>,
  boolean
>

/** The three phases a task is congelato in (D-7): closing or being validated. */
const TERMINAL_GROUPS: TaskStatusGroupValue[] = ['in_validation', 'closed_positive', 'closed_negative']

/**
 * Ready-made `status_groups[]` for-select param for the "richiedi
 * validazione" picker of the complete dialog (AC-043), hoisted at module
 * level: an inline object literal would be a new reference on every render
 * (frontend.md §10).
 */
export const IN_VALIDATION_GROUP_PARAMS: Record<string, TaskStatusGroupValue[]> = {
  status_groups: ['in_validation'],
}

/** A task blocked by `unblock` alone: every other action is suspended (D-8). */
function blockedAvailability(): TaskActionAvailabilityFlags {
  return {
    complete: false,
    uncomplete: false,
    approve: false,
    reject: false,
    block: false,
    unblock: true,
    request_update: false,
  }
}

export function taskActionAvailability(task: TaskDetail): TaskActionAvailabilityFlags {
  if (task.is_blocked) {
    return blockedAvailability()
  }

  const group = task.task_status.group
  const terminal = TERMINAL_GROUPS.includes(group)
  const validating = group === 'in_validation'

  return {
    complete: !terminal,
    uncomplete: terminal,
    approve: validating,
    reject: validating,
    block: true,
    unblock: false,
    // Spec 0118 D-10: "solo su task aperti", which is the SAME condition as
    // complete — deliberately the same expression, not a second rule that
    // could drift from it.
    request_update: !terminal,
  }
}
