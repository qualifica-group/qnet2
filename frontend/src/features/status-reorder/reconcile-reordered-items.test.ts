import { describe, expect, it } from 'vitest'
import { reconcileReorderedItems } from '@/features/status-reorder/reconcile-reordered-items'
import type { ReorderedStatusEntry } from '@/features/status-reorder/types'

/**
 * Spec 0068 D-5. Direct unit test of the pure mapping, independent of
 * `useStatusReorder`'s list-query resync (see that hook's doc and
 * `status-reorder-sheet.test.tsx` for why the DOM-level test cannot
 * discriminate this fallback on its own).
 */
describe('reconcileReorderedItems (spec 0068 D-5)', () => {
  it('sorts by sort_order and resolves each entry name from nameById', () => {
    const fresh: ReorderedStatusEntry[] = [
      { id: 2, sort_order: 20, system_key: null },
      { id: 1, sort_order: 10, system_key: 'new' },
    ]
    const nameById = new Map([
      [1, 'New'],
      [2, 'Alpha'],
    ])

    expect(reconcileReorderedItems(fresh, nameById)).toEqual([
      { id: 1, systemKey: 'new', name: 'New' },
      { id: 2, systemKey: null, name: 'Alpha' },
    ])
  })

  it('falls back to null when an entry omits system_key entirely', () => {
    const fresh: ReorderedStatusEntry[] = [
      { id: 1, sort_order: 10 },
      { id: 2, sort_order: 20 },
    ]
    const nameById = new Map([
      [1, 'Bank transfer'],
      [2, 'Cash'],
    ])

    const reconciled = reconcileReorderedItems(fresh, nameById)

    expect(reconciled.every((item) => item.systemKey === null)).toBe(true)
    expect(reconciled.map((item) => item.systemKey)).not.toContain(undefined)
  })

  it('resolves an unresolvable id to an empty name rather than throwing', () => {
    const fresh: ReorderedStatusEntry[] = [{ id: 99, sort_order: 10, system_key: null }]

    expect(reconcileReorderedItems(fresh, new Map())).toEqual([
      { id: 99, systemKey: null, name: '' },
    ])
  })
})
