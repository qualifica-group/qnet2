import { useMemo } from 'react'
import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { fetchForSelect, FOR_SELECT_PAGE_SIZE } from '@/features/for-select/api'
import { forSelectKeys } from '@/features/for-select/query-keys'
import type { ForSelectItem } from '@/features/for-select/types'

interface UseForSelectOptions {
  /** Resource segment of the endpoint, e.g. `users` → `/users/for-select`. */
  resource: string
  /** Debounced server-side search term. */
  search: string
  /** Ids of already-selected values to hydrate on the first page (edit mode). */
  ids?: number[]
  /** Gates the query so it only runs when the consumer needs the data. */
  enabled?: boolean
  /**
   * Extra, resource-specific query parameters (spec 0032 `dependency.param`),
   * sent on every page and included in the query key so a parent value change
   * starts a fresh paginated query. Array values are serialized as repeated
   * `key[]=` params (Laravel convention).
   */
  params?: Record<string, string | number | string[] | number[]>
}

/**
 * Reusable infinite query for any entity-backed for-select endpoint (ADR 0011).
 * Offset-based pagination mirroring the notifications list: `getNextPageParam`
 * derives the next offset from the envelope and returns undefined once every row
 * is loaded. The selected `ids` hydrate only the first page (they are explicit
 * and bypass `search`); they are intentionally excluded from the query key so a
 * selection change never refetches the whole list.
 */
export function useForSelect({
  resource,
  search,
  ids,
  enabled = true,
  params,
}: UseForSelectOptions) {
  return useInfiniteQuery({
    queryKey: forSelectKeys.list(resource, search, params),
    queryFn: ({ pageParam }) =>
      fetchForSelect(resource, {
        search,
        offset: pageParam,
        limit: FOR_SELECT_PAGE_SIZE,
        // Hydrate selected values only on the first page to label current
        // selections that fall outside the searched window.
        ids: pageParam === 0 ? ids : undefined,
        params,
      }),
    initialPageParam: 0,
    getNextPageParam: (lastPage) => {
      const { offset, limit, total } = lastPage.pagination
      const nextOffset = offset + limit
      return nextOffset < total ? nextOffset : undefined
    },
    enabled,
  })
}

interface UseForSelectLabelsOptions {
  /** Resource segment of the endpoint, e.g. `users` → `/users/for-select`. */
  resource: string
  /** Ids whose `{id, label, avatar_url}` must be resolved for the trigger. */
  ids: number[]
  /** Gates the query so it only runs when a label actually needs resolving. */
  enabled?: boolean
  /** Extra, resource-specific query parameters (spec 0032 `dependency.param`). */
  params?: Record<string, string | number | string[] | number[]>
}

/**
 * Resolves the labels of an explicit id set independently of the paginated
 * search list. The selected value's trigger label MUST NOT depend on the
 * shared, ids-less `useForSelect` cache: sibling selects on the same resource
 * share that one entry, so only the first instance's `ids` reach the server and
 * every other selection falls back to `#id`. Keying by ids (see
 * `forSelectKeys.labels`) gives each selection its own collision-free entry. The
 * backend returns the requested ids in addition to the page, so a single first
 * page always covers a bounded selection (managers ≤ 4, relation sets small).
 */
export function useForSelectLabels({
  resource,
  ids,
  enabled = true,
  params,
}: UseForSelectLabelsOptions): Map<number, ForSelectItem> {
  // Sorted so the query key is identity-stable regardless of selection order.
  const sortedIds = useMemo(() => [...ids].sort((a, b) => a - b), [ids])

  const { data } = useQuery({
    queryKey: forSelectKeys.labels(resource, sortedIds, params),
    queryFn: () =>
      fetchForSelect(resource, {
        offset: 0,
        limit: Math.min(Math.max(sortedIds.length, 1), 100),
        ids: sortedIds,
        params,
      }),
    enabled: enabled && sortedIds.length > 0,
    // Labels for a fixed id set are stable within a session; avoid refetch churn.
    staleTime: 5 * 60 * 1000,
  })

  return useMemo(() => {
    const map = new Map<number, ForSelectItem>()
    for (const item of data?.items ?? []) {
      map.set(item.id, item)
    }
    return map
  }, [data])
}

/** Flattens the loaded pages into a single de-duplicated option list (by id). */
export function flattenForSelectPages(
  pages: { items: ForSelectItem[] }[] | undefined,
): ForSelectItem[] {
  if (!pages) {
    return []
  }
  const seen = new Set<number>()
  const result: ForSelectItem[] = []
  for (const page of pages) {
    for (const item of page.items) {
      if (!seen.has(item.id)) {
        seen.add(item.id)
        result.push(item)
      }
    }
  }
  return result
}
