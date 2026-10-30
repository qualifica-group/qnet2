/**
 * Pure helpers of the Task board's bulk selection (D-7): the tri-state of a
 * "select all" checkbox over a set of root ids, and the selection set after
 * selecting/deselecting those ids at once.
 */

export type SelectAllState = boolean | 'indeterminate'

export function selectAllState(taskIds: readonly number[], selectedTaskIds: ReadonlySet<number>): SelectAllState {
  const selectedCount = taskIds.filter((id) => selectedTaskIds.has(id)).length
  if (selectedCount === 0) {
    return false
  }
  return selectedCount === taskIds.length ? true : 'indeterminate'
}

export function setIdsSelection(ids: ReadonlySet<number>, taskIds: readonly number[], isSelected: boolean): Set<number> {
  const next = new Set(ids)
  for (const id of taskIds) {
    if (isSelected) {
      next.add(id)
    } else {
      next.delete(id)
    }
  }
  return next
}
