import type { RootSplitGroup } from '@/features/request-management/root-split-names'

/** A file chosen in "Nuova richiesta": uploaded right after the save, onto the offer of its parent category (spec 0415, D-4). */
export interface PendingRequestDocument {
  key: number
  file: File
  /** Parent category whose offer receives the file; null while the lines span at most one. */
  rootId: number | null
}

/**
 * The parent category a file actually goes to: the one picked, while it is
 * still among the lines' parent categories; otherwise the first one (the
 * default, also when the picked one was removed from the lines).
 */
export function effectiveRootId(rootId: number | null, groups: RootSplitGroup[]): number | null {
  return groups.some((group) => group.id === rootId) ? rootId : (groups[0]?.id ?? null)
}

/**
 * The created offer a file lands on: `created_quote_ids` follows the order of
 * first appearance of the parent categories (spec 0411, D-2), the same order
 * as `groups`. Anything unmatched falls back to the first offer.
 */
export function targetQuoteId(rootId: number | null, groups: RootSplitGroup[], createdQuoteIds: number[]): number {
  const position = groups.findIndex((group) => group.id === rootId)

  return createdQuoteIds[position] ?? createdQuoteIds[0]
}
