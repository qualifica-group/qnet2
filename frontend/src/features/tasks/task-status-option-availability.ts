/**
 * Pure mirror of spec 0123 D-4/D-5: WHICH options of the Stato select a PATCH
 * could never reach. `task-classification-section.tsx` only wires the
 * result via `RelationSelectField.isItemDisabled`, never branches on
 * `meta.group` itself — the server re-asserts the same rule regardless
 * (422 `task_status_id`, D-4).
 */

import type { ForSelectItem } from '@/features/for-select/types'
import { taskStatusMetaOf } from '@/features/tasks/for-select-api'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

/** Reachable only through Completa/Approva (D-4): a PATCH to either always 422s, for any actor. */
const ACTION_ONLY_GROUPS: TaskStatusGroupValue[] = ['in_validation', 'closed_positive']

/**
 * Spec 0154 D-10: on CREATE a manually picked initial status may be
 * `open`/`pending` only — every closing phase (positive AND negative, unlike
 * the PATCH rule above) plus `in_validation` 422s unconditionally, with no
 * `close_via_status`-style exception: a brand-new task carries no action
 * mandate yet to gate on.
 */
const CREATE_DISALLOWED_GROUPS: TaskStatusGroupValue[] = ['in_validation', 'closed_positive', 'closed_negative']

/**
 * Mirror of the D-10 create-time rule. An option with no `meta` at all is
 * never disabled by this rule either, same tolerance as `taskStatusMetaOf`.
 */
export function isTaskStatusOptionDisabledOnCreate(item: ForSelectItem): boolean {
  const meta = taskStatusMetaOf(item)
  if (!meta) {
    return false
  }
  return CREATE_DISALLOWED_GROUPS.includes(meta.group)
}

/**
 * `in_validation`/`closed_positive` are disabled unconditionally;
 * `closed_negative` is disabled exactly when the actor's own
 * `close_via_status` flag (`permissions.actions.close_via_status`, D-5) is
 * false. Every other option stays selectable. An option with no `meta` at
 * all (route not yet registered, mirrors `taskStatusMetaOf`'s own tolerance)
 * is never disabled by this rule.
 */
export function isTaskStatusOptionDisabled(item: ForSelectItem, closeViaStatus: boolean): boolean {
  const meta = taskStatusMetaOf(item)
  if (!meta) {
    return false
  }
  if (ACTION_ONLY_GROUPS.includes(meta.group)) {
    return true
  }
  return meta.group === 'closed_negative' && !closeViaStatus
}
