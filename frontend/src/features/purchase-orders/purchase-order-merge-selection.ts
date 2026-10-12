import type { MergeCandidate } from '@/features/purchase-orders/types'

export type OrderSelectionState = 'all' | 'some' | 'none'

/** Selection is a set of line ids: picking a whole order is just picking all of its lines. */
export function orderSelectionState(candidate: MergeCandidate, selected: ReadonlySet<number>): OrderSelectionState {
  const picked = candidate.lines.filter((line) => selected.has(line.id)).length
  if (picked === 0) return 'none'
  return picked === candidate.lines.length ? 'all' : 'some'
}

/** A whole order that is not fully selected becomes fully selected; a fully selected one is cleared. */
export function toggleOrder(candidate: MergeCandidate, selected: ReadonlySet<number>): Set<number> {
  const next = new Set(selected)
  const clear = orderSelectionState(candidate, selected) === 'all'
  for (const line of candidate.lines) {
    if (clear) {
      next.delete(line.id)
    } else {
      next.add(line.id)
    }
  }
  return next
}

export function toggleLine(lineId: number, selected: ReadonlySet<number>): Set<number> {
  const next = new Set(selected)
  if (!next.delete(lineId)) {
    next.add(lineId)
  }
  return next
}
