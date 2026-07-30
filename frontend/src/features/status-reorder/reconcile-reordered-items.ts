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
 * `nameById` supplies the display name: the reorder response itself carries
 * no `name`, only id/sort_order/system_key.
 */
export function reconcileReorderedItems(
  fresh: ReorderedStatusEntry[],
  nameById: Map<number, string>,
): StatusReorderItem[] {
  return [...fresh]
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((entry) => ({
      id: entry.id,
      systemKey: entry.system_key ?? null,
      name: nameById.get(entry.id) ?? '',
    }))
}
