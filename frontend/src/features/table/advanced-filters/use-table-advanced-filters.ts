import { useMemo } from 'react'
import {
  useAdvancedFilters,
  type UseAdvancedFiltersResult,
} from '@/features/table/advanced-filters/use-advanced-filters'
import type {
  AdvancedFilterDescriptor,
  AdvancedFilterValues,
} from '@/features/table/advanced-filters/types'

interface UseTableAdvancedFiltersArgs {
  domain: string
  /** `TableConfig.advancedFilters`, undefined until the config loads. */
  descriptors: AdvancedFilterDescriptor[] | undefined
  /** `TableConfig.appliedAdvancedFilters`. */
  applied: AdvancedFilterValues | null | undefined
  /** Invoked once after Apply/Reset persists, to purge-reload the grid exactly once. */
  onApplied: () => void
  /** Forwarded verbatim to `useAdvancedFilters` (spec 0151 D-2). */
  override?: AdvancedFilterValues | null
  /** Forwarded verbatim to `useAdvancedFilters` (spec 0151 D-2). */
  onOverrideCleared?: () => void
}

export interface TableAdvancedFiltersState {
  /** Stable-identity descriptor list (`[]` while the config is still loading). */
  descriptors: AdvancedFilterDescriptor[]
  filters: UseAdvancedFiltersResult
}

/**
 * Thin composition wrapper around `useAdvancedFilters` for `TableView`
 * (engineering.md §6): resolves the descriptor catalog to a stable, non-null
 * array and bundles it with the draft/applied state, so the orchestrator only
 * holds one value instead of memoizing the descriptors itself.
 */
export function useTableAdvancedFilters({
  domain,
  descriptors,
  applied,
  onApplied,
  override,
  onOverrideCleared,
}: UseTableAdvancedFiltersArgs): TableAdvancedFiltersState {
  const resolvedDescriptors = useMemo(() => descriptors ?? [], [descriptors])
  const filters = useAdvancedFilters({
    domain,
    descriptors: resolvedDescriptors,
    applied,
    onApplied,
    override,
    onOverrideCleared,
  })

  return { descriptors: resolvedDescriptors, filters }
}
