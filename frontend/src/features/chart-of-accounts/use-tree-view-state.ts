import { useMemo, useState } from 'react'
import { buildRows, defaultExpandedKeys, expandableKeys, type TreeRow } from '@/features/chart-of-accounts/tree-model'
import type { ChartTree } from '@/features/chart-of-accounts/types'

/**
 * Expansion + search state of the tree. The expansion set is never touched by
 * the search (it is bypassed while a query is active), so clearing the search
 * restores the previous expansion. `null` means "not touched yet": the default.
 */
export function useTreeViewState(tree: ChartTree) {
  const [expandedOverride, setExpandedOverride] = useState<Set<string> | null>(null)
  const [query, setQuery] = useState('')

  const expanded = useMemo(() => expandedOverride ?? defaultExpandedKeys(tree), [expandedOverride, tree])
  const rows: TreeRow[] = useMemo(() => buildRows(tree, expanded, query), [tree, expanded, query])

  const toggle = (key: string, open?: boolean) => {
    const next = new Set(expanded)
    const shouldOpen = open ?? !next.has(key)
    if (shouldOpen) next.add(key)
    else next.delete(key)
    setExpandedOverride(next)
  }

  return {
    rows,
    query,
    isSearching: query.trim() !== '',
    setQuery,
    toggle,
    expandAll: () => setExpandedOverride(new Set(expandableKeys(tree))),
    collapseAll: () => setExpandedOverride(new Set()),
  }
}
