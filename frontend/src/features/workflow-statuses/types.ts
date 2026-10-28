/**
 * Domain-agnostic shape of one editable workflow-status row, shared by the
 * quote and the work-order configurators. Each domain narrows `group` and
 * `system_key` to its own vocabulary (see `quote-workflows/types.ts`) and
 * opts into the optional per-domain fields: `requires_note` (quotes) and
 * `progress_percentage` (work orders).
 */
export interface WorkflowStatusFormRow {
  /** String identity `<SortableList>` requires; `statusId` is the persisted backend id. */
  id: string
  statusId?: number
  name: string
  description: string | null
  color: string | null
  group: string
  system_key: string | null
  requires_note?: boolean
  progress_percentage?: number | null
}

/** The row fields the editor may patch (everything but the row identity and its pinned `system_key`). */
export type WorkflowStatusRowPatch<R extends WorkflowStatusFormRow = WorkflowStatusFormRow> = Partial<
  Pick<R, 'name' | 'description' | 'color' | 'group' | 'requires_note' | 'progress_percentage'>
>

/** One entry of the group select; `label` is already translated by the domain that owns the vocabulary. */
export interface WorkflowStatusGroupOption {
  value: string
  label: string
  /** Soft-badge classes shown for a pinned (system) row, whose group is read-only. */
  badgeClassName: string
}
