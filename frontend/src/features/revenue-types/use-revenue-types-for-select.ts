import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchRevenueTypesForSelect } from '@/features/revenue-types/api'
import { revenueTypeKeys } from '@/features/revenue-types/query-keys'
import { groupRevenueTypes } from '@/features/revenue-types/group-options'
import type { RevenueTypeOption } from '@/features/revenue-types/types'

interface UseRevenueTypeOptionsArgs {
  search: string
  /** Gates the query: the popup only fetches while open. */
  enabled: boolean
}

/** Selectable sub-accounts (active, with active account and group), searched server-side by code or name. */
export function useRevenueTypeOptions({ search, enabled }: UseRevenueTypeOptionsArgs) {
  const query = useQuery({
    queryKey: revenueTypeKeys.list(search),
    queryFn: () => fetchRevenueTypesForSelect({ search }),
    enabled,
    staleTime: 60 * 1000,
  })
  const groups = useMemo(() => groupRevenueTypes(query.data?.items ?? []), [query.data])
  return { ...query, groups }
}

const NO_IDS: number[] = []

/** Stable id list for a nullable id (a fresh array per render would churn the label query key). */
export function idList(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

/** Resolves explicit ids (inactive ones included) to their options, for values the list may not show. */
export function useRevenueTypeLabels(ids: number[], enabled = true): Map<number, RevenueTypeOption> {
  const sorted = useMemo(() => [...ids].sort((a, b) => a - b), [ids])
  const { data } = useQuery({
    queryKey: revenueTypeKeys.labels(sorted),
    queryFn: () => fetchRevenueTypesForSelect({ ids: sorted, limit: Math.min(Math.max(sorted.length, 1), 100) }),
    enabled: enabled && sorted.length > 0,
    staleTime: 5 * 60 * 1000,
  })
  return useMemo(() => new Map((data?.items ?? []).map((item) => [item.id, item])), [data])
}
