/**
 * Pure grouping for the "per stato" Kanban (spec 0157 D-2/D-3): one column per
 * active status, in the catalog's own order (`sort_order`, already the order
 * `task-statuses/for-select` returns). No "Senza stato" column — a task's
 * status is never empty.
 */
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'
import type { TaskKanbanGroup } from '@/features/tasks/task-kanban/task-kanban-types'

/**
 * Mirrors the backend's `TaskManualStatusGuard::MANUAL_GROUPS`: the only two
 * phases a status may be picked into MANUALLY (open/pending). Every other
 * phase (in_validation, closed_positive, closed_negative) is reached only
 * through a workflow action, never a bare create.
 */
const MANUAL_STATUS_GROUPS: ReadonlySet<TaskStatusGroupValue> = new Set(['open', 'pending'])

/**
 * A drop is always allowed (D-3: an open<->open move PATCHes, a move into a
 * closing status opens "Completa", a move out of a closed one reopens) — the
 * DESTINATION decides the mutation, not whether the column accepts a drop.
 */
const STATUS_COLUMN_DROPPABLE = true

/** Every status column allows dragging out (its own status transitions decide what happens, D-3). */
const STATUS_COLUMN_DRAGGABLE = true

/**
 * Builds one column per status catalog entry, in catalog order (spec 0164
 * D-1: metadata only — each column loads its OWN rows via `kanbanGroup`, so
 * a status with no matching task still renders an empty column with its own
 * live count, keeping the full workflow visible).
 */
export function buildTaskStatusKanbanGroups(statuses: TaskStatusForSelectItem[]): TaskKanbanGroup<string>[] {
  return statuses.map((status) => ({
    key: String(status.id),
    label: status.label,
    color: status.meta.color,
    droppable: STATUS_COLUMN_DROPPABLE,
    draggable: STATUS_COLUMN_DRAGGABLE,
    kanbanGroup: { by: 'status', key: status.id },
  }))
}

/**
 * Whether the column's "+" affordance may create a task directly on this
 * status (spec 0157 D-4): only a status in `TaskManualStatusGroups` is a
 * valid MANUAL initial status server-side (`TaskManualStatusGuard`); a
 * closing/in-validation status is never offered here, mirroring the same
 * restriction the create form's own status picker already enforces.
 */
export function isManualStatusColumn(group: TaskStatusGroupValue): boolean {
  return MANUAL_STATUS_GROUPS.has(group)
}
