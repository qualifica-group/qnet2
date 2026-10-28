import type { WorkflowStatusFormRow, WorkflowStatusRowPatch } from '@/features/workflow-statuses/types'

/** Inserts a new custom row before the pinned tail (the tail always stays last). */
export function withCustomRow<R extends { system_key: string | null }>(
  rows: R[],
  newRow: R,
  isTailKey: (key: R['system_key']) => boolean,
): R[] {
  const tailIndex = rows.findIndex((row) => isTailKey(row.system_key))
  const insertAt = tailIndex === -1 ? rows.length : tailIndex
  return [...rows.slice(0, insertAt), newRow, ...rows.slice(insertAt)]
}

export function withoutRow<R extends { id: string }>(rows: R[], id: string): R[] {
  return rows.filter((row) => row.id !== id)
}

export function withPatchedRow<R extends WorkflowStatusFormRow>(rows: R[], id: string, patch: WorkflowStatusRowPatch<R>): R[] {
  return rows.map((row) => (row.id === id ? { ...row, ...patch } : row))
}

export function inOrder<R extends { id: string }>(rows: R[], orderedIds: string[]): R[] {
  const byId = new Map(rows.map((row) => [row.id, row]))
  return orderedIds.map((id) => byId.get(id)).filter((row): row is R => row !== undefined)
}
