import { describe, expect, it } from 'vitest'
import { reconcileReorderedItems } from '@/features/status-reorder/reconcile-reordered-items'
import type {
  ReorderedStatusEntry,
  StatusReorderItem,
} from '@/features/status-reorder/types'

/**
 * Spec 0068 D-5. Direct unit test of the pure mapping, independent of
 * `useStatusReorder`'s list-query resync (see that hook's doc and
 * `status-reorder-sheet.test.tsx` for why the DOM-level test cannot
 * discriminate this fallback on its own).
 */
/** Indexes the pre-drag items the way `useStatusReorder` does. */
function previous(items: StatusReorderItem[]): Map<number, StatusReorderItem> {
  return new Map(items.map((item) => [item.id, item]))
}

describe('reconcileReorderedItems (spec 0068 D-5)', () => {
  it('sorts by sort_order and resolves each entry name from nameById', () => {
    const fresh: ReorderedStatusEntry[] = [
      { id: 2, sort_order: 20, system_key: null },
      { id: 1, sort_order: 10, system_key: 'new' },
    ]
    const previousById = previous([
      { id: 1, name: 'New', systemKey: 'new' },
      { id: 2, name: 'Alpha', systemKey: null },
    ])

    expect(reconcileReorderedItems(fresh, previousById)).toEqual([
      { id: 1, systemKey: 'new', name: 'New' },
      { id: 2, systemKey: null, name: 'Alpha' },
    ])
  })

  it('falls back to null when an entry omits system_key entirely', () => {
    const fresh: ReorderedStatusEntry[] = [
      { id: 1, sort_order: 10 },
      { id: 2, sort_order: 20 },
    ]
    const previousById = previous([
      { id: 1, name: 'Bank transfer', systemKey: null },
      { id: 2, name: 'Cash', systemKey: null },
    ])

    const reconciled = reconcileReorderedItems(fresh, previousById)

    expect(reconciled.every((item) => item.systemKey === null)).toBe(true)
    expect(reconciled.map((item) => item.systemKey)).not.toContain(undefined)
  })

  it('carries isActive over the drag, so the inactive marker does not vanish (spec 0101)', () => {
    // The reorder response carries no `is_active`; without the carry-over the
    // badge would disappear on the first successful drag.
    const fresh: ReorderedStatusEntry[] = [
      { id: 1, sort_order: 10 },
      { id: 2, sort_order: 20 },
    ]
    const previousById = previous([
      { id: 1, name: 'Alta', systemKey: null, isActive: true },
      { id: 2, name: 'Obsoleta', systemKey: null, isActive: false },
    ])

    expect(reconcileReorderedItems(fresh, previousById).map((item) => item.isActive)).toEqual([
      true,
      false,
    ])
  })

  it('leaves isActive undefined for a resource that projects none', () => {
    const fresh: ReorderedStatusEntry[] = [{ id: 1, sort_order: 10 }]
    const previousById = previous([{ id: 1, name: 'Bonifico', systemKey: null }])

    expect(reconcileReorderedItems(fresh, previousById)[0].isActive).toBeUndefined()
  })

  it('resolves an unresolvable id to an empty name rather than throwing', () => {
    const fresh: ReorderedStatusEntry[] = [{ id: 99, sort_order: 10, system_key: null }]

    expect(reconcileReorderedItems(fresh, new Map())).toEqual([
      { id: 99, systemKey: null, name: '' },
    ])
  })
})
