import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TaskDetail, TaskFormMode, TaskNamedRef, TaskWorkOrderStageRef } from '@/features/tasks/types'

/*
 * Picker hydration of the task form fields: projects the persisted (edit) or
 * source (duplicate) task onto the `{id, name}` shape every relation picker
 * reads. Shared by the create form and the detail's in-place editors (spec 0195).
 */

/** Stable module-level references: a fresh `[]`/`null` per render would break memo/dep stability. */
const EMPTY_PEOPLE: RelationFieldRef[] = []

/** Separator between a commessa's code and title, matching `WorkOrderForSelectResource::LABEL_SEPARATOR`. */
const WORK_ORDER_LABEL_SEPARATOR = ' — '

/**
 * The "clona" source (spec 0156 D-4), `null` outside duplicate mode: feeds
 * ONLY the relation-picker display hydration (registry, referent,
 * opportunity, commessa/fase, lead, requester, assignees, watchers) —
 * everything but the parent link (deliberately excluded, "il padre non si
 * copia") and everything `persistedTask` alone drives (the header/summary's
 * own "Modifica"/"Nuovo" framing, the classification section's edit-vs-create
 * status rule), which must read as a bare create even though the fields
 * start prefilled.
 */
export function duplicateSource(mode: TaskFormMode): TaskDetail | null {
  return mode.type === 'duplicate' ? mode.source : null
}

/** `{id, title}` projected onto the `{id, name}` shape every relation picker hydrates from. */
export function parentRefOf(task: TaskDetail | null): RelationFieldRef | null {
  return task?.parent_task ? { id: task.parent_task.id, name: task.parent_task.title } : null
}

/**
 * `{id, code, title}` projected onto the `{id, name}` shape the picker
 * hydrates from, composed EXACTLY like `WorkOrderForSelectResource` composes
 * its own `label` — empty-title fallback to the bare code included — so the
 * trigger label and the list options can never read differently.
 *
 * This hydration comes from the TASK's own detail, not from the for-select,
 * which matters: `GET /api/work-orders/for-select` is narrowed by
 * `WorkOrderVisibilityScope`, and `ids[]` deliberately does not bypass it (a
 * blanket bypass would turn the endpoint into an enumeration oracle). A user
 * without visibility on the linked commessa still sees its correct label
 * here, because D-9 does not obscure linked-record labels.
 *
 * The same ref is ALSO passed as the picker's `pinned` option, so the
 * persisted value stays selectable after the user changes the pick — the
 * option list alone would have dropped it. Nothing new is disclosed: it is
 * the value this form already loaded.
 */
export function workOrderRefOf(task: TaskDetail | null): RelationFieldRef | null {
  const workOrder = task?.work_order
  if (!workOrder) {
    return null
  }
  const title = workOrder.title.trim()
  const name = title === '' ? workOrder.code : `${workOrder.code}${WORK_ORDER_LABEL_SEPARATOR}${title}`
  return { id: workOrder.id, name }
}

/** The persisted fase (spec 0146 D-3), possibly closed — `useTaskWorkOrderStageOptions` keeps it selectable regardless. */
export function workOrderStageOf(task: TaskDetail | null): TaskWorkOrderStageRef | null {
  return task?.work_order_stage ?? null
}

/** `{id, label}` (spec 0154 D-4) projected onto the `{id, name}` shape every relation picker hydrates from. */
export function leadRefOf(task: TaskDetail | null): RelationFieldRef | null {
  return task?.lead ? { id: task.lead.id, name: task.lead.label } : null
}

export function peopleOf(refs: TaskNamedRef[] | undefined): RelationFieldRef[] {
  return refs && refs.length > 0 ? refs : EMPTY_PEOPLE
}

/**
 * The Richiedente picker's hydration: the persisted requester in edit mode
 * (possibly `null` on a historical row, AC-008 — no retroactive sanatoria),
 * or the connected actor's own ref on create (D-1 prefill, `useTaskForm`).
 */
export function requesterRefOf(
  task: TaskDetail | null,
  currentUserRef: RelationFieldRef | null,
): RelationFieldRef | null {
  return task ? (task.requester ?? null) : currentUserRef
}
