import type {
  ReorderedStatusEntry,
  StatusReorderItem,
} from '@/features/status-reorder/types'

/**
 * Pure mapping from the fresh, full list returned by `POST /{resource}/reorder`
 * back into the sheet's local `StatusReorderItem[]` shape, sorted by the
 * server-resequenced `sort_order`. Extracted out of `useStatusReorder`'s
 * `.then()` handler so the D-5 hardening — a response entry omitting
 * `system_key` must resolve to `systemKey: null`, never `undefined` (which
 * `isPinned={(row) => row.systemKey !== null}` in `StatusReorderSheet` would
 * read as pinned for every row) — is directly unit-testable, independent of
 * `useStatusReorder`'s own list-query resync (spec 0068 D-5; the resync path
 * has a separate, documented, out-of-scope issue that makes this mapping's
 * output unobservable through the full Sheet — see
 * `status-reorder-sheet.test.tsx`).
 *
 * `previousById` supplies everything the response does NOT carry — it holds
 * only id/sort_order/system_key, so both the display `name` and `isActive`
 * (spec 0101) have to be carried over from the pre-drag items. Dropping
 * `isActive` here would make the "inactive" marker vanish on the first
 * successful drag, which reads as unreliable data rather than as a refresh.
 */
export function reconcileReorderedItems(
  fresh: ReorderedStatusEntry[],
  previousById: Map<number, StatusReorderItem>,
): StatusReorderItem[] {
  return [...fresh]
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((entry) => {
      const previous = previousById.get(entry.id)

      return {
        id: entry.id,
        systemKey: entry.system_key ?? null,
        name: previous?.name ?? '',
        isActive: previous?.isActive,
      }
    })
}
