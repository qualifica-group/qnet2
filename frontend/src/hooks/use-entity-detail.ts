import { useQuery, type QueryKey } from '@tanstack/react-query'

/** What `useEntityDetail` exposes to a detail/edit card. */
export interface EntityDetailResult<T> {
  /** Fresh entity detail, or `undefined` while the on-open fetch is in flight. */
  data: T | undefined
  /**
   * `true` until the fetch triggered by opening the card has settled. Consumers
   * must render a skeleton while this is `true` and only mount the form/detail
   * once it is `false`, so the view always reflects authoritative server values
   * and edit forms never initialize from a stale cached snapshot.
   */
  isLoading: boolean
  /** The on-open fetch failed. Pair with `refetch` for a retry affordance. */
  isError: boolean
  /** The raw error the fetch rejected with, e.g. for a caller that branches on the HTTP status (spec 0155 D-7). */
  error: unknown
  /** Re-runs the fetch (used by the error retry action). */
  refetch: () => void
}

/**
 * Standard "fresh detail on open" pattern for entity cards (view + edit Sheets).
 *
 * Opening a card always refetches the `show` endpoint and keeps `isLoading` true
 * until that fetch settles — even when a stale snapshot is already cached from a
 * previous open. This guarantees the card reflects the latest, re-authorized
 * server state and that edit forms capture authoritative `defaultValues` rather
 * than an outdated grid-row/cache snapshot.
 *
 * See `standards/coding-standards.md` → "Entity Detail Cards (Fresh On Open)".
 */
export interface EntityDetailOptions {
  /**
   * `false`: the skeleton covers only the fetch made on open; a later refetch
   * (an invalidation after a save somewhere inside the card) swaps the data in
   * place, so the card is not unmounted and keeps its own state (active tab,
   * scroll, open sections). Only for a card whose forms re-sync on new data.
   * Default `true` (unchanged behaviour).
   */
  skeletonOnRefetch?: boolean
}

export function useEntityDetail<T>(
  queryKey: QueryKey,
  queryFn: () => Promise<T>,
  enabled = true,
  { skeletonOnRefetch = true }: EntityDetailOptions = {},
): EntityDetailResult<T> {
  const query = useQuery({
    queryKey,
    queryFn,
    enabled,
    staleTime: 0,
    refetchOnMount: 'always',
  })

  return {
    data: query.data,
    isError: query.isError,
    error: query.error,
    refetch: () => void query.refetch(),
    // `isPending` covers the cold cache; `isFetching` keeps the skeleton up while
    // the on-open refetch replaces an existing (stale) snapshot. Guarded by
    // `enabled` so a deferred card does not spin forever before it is allowed to
    // fetch.
    // `isFetchedAfterMount` stays true once the on-open fetch has settled, so a
    // later background refetch does not bring the skeleton back.
    isLoading: enabled && (query.isPending || (skeletonOnRefetch ? query.isFetching : !query.isFetchedAfterMount)),
  }
}
