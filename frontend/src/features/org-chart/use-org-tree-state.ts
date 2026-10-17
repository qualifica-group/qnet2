import { useCallback, useMemo, useState } from 'react'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { collectExpandableKeys, filterTree } from '@/features/org-chart/tree-utils'

const NO_COLLAPSED_KEYS: ReadonlySet<string> = new Set()

/**
 * Client-side state of one org-chart tree: search box, collapsed branches
 * (default: everything expanded) and the expand/collapse-all toggle. While a
 * search is active every branch is forced open so each result is visible.
 */
export function useOrgTreeState(allNodes: OrgTreeNode[]) {
  const [search, setSearch] = useState('')
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(() => new Set())

  const nodes = useMemo(() => filterTree(allNodes, search), [allNodes, search])
  const expandableKeys = useMemo(() => collectExpandableKeys(nodes), [nodes])
  const isSearching = search.trim() !== ''

  const toggle = useCallback((key: string) => {
    setCollapsed((current) => {
      const next = new Set(current)
      if (!next.delete(key)) next.add(key)
      return next
    })
  }, [])

  const expandAll = useCallback(() => setCollapsed(new Set()), [])
  const collapseAll = useCallback(() => setCollapsed(new Set(expandableKeys)), [expandableKeys])

  return {
    search,
    setSearch,
    nodes,
    isSearching,
    hasExpandable: expandableKeys.length > 0,
    allCollapsed: expandableKeys.length > 0 && expandableKeys.every((key) => collapsed.has(key)),
    collapsedKeys: isSearching ? NO_COLLAPSED_KEYS : collapsed,
    toggle,
    expandAll,
    collapseAll,
  }
}
