import {
  isTailWorkflowSystemKey,
  type WorkflowStatusFormRow,
} from '@/features/opportunity-workflows/types'

/** The group a row falls back to when it loses the `validated` mark (same default a freshly added custom row gets). */
const UNMARKED_GROUP = 'pending' as const

/**
 * Moves the OPTIONAL `validated` mark onto the row `id` (or removes it when
 * `marked` is false) — user directive 2026-08-03: at most one row per set
 * carries it, and by default none does.
 *
 * Marking also moves the row to where the backend will persist it (right
 * before the `closed_won`/`closed_lost` tail, `WorkflowStatusWriter`), so the
 * local order never contradicts what a save produces; unmarking sends it back
 * to the end of the custom rows. The mandatory pinned rows
 * (`open`/`closed_won`/`closed_lost`) are never touched.
 */
export function markValidatedRow(
  rows: WorkflowStatusFormRow[],
  id: string,
  marked: boolean,
): WorkflowStatusFormRow[] {
  const target = rows.find((row) => row.id === id)
  if (!target || (target.system_key !== null && target.system_key !== 'validated')) {
    return rows
  }

  // Step 1: clear the previous mark, then apply the new state to the target.
  const rest = rows
    .filter((row) => row.id !== id)
    .map((row) =>
      row.system_key === 'validated' ? { ...row, system_key: null, group: UNMARKED_GROUP } : row,
    )

  const next: WorkflowStatusFormRow = marked
    ? { ...target, system_key: 'validated', group: 'validated' }
    : { ...target, system_key: null, group: UNMARKED_GROUP }

  // Step 2: reinsert it where it will be persisted — before the pinned tail
  // when marked, as the last custom row when not.
  const tailIndex = rest.findIndex((row) => isTailWorkflowSystemKey(row.system_key))
  const insertAt = tailIndex === -1 ? rest.length : tailIndex

  return [...rest.slice(0, insertAt), next, ...rest.slice(insertAt)]
}
