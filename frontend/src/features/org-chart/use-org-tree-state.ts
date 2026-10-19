import { useCallback, useMemo, useState } from 'react'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { collectExpandableKeys, filterTree } from '@/features/org-chart/tree-utils'

const NO_COLLAPSED_KEYS: ReadonlySet<string> = new Set()

/**
 * Client-side state of one org chart: search box, expanded cards and the
 * expand/collapse-all toggle. Until the user touches a branch only the roots
 * are open (spec 0274 D-4), so a large chart starts readable. While a search is
 * active every branch is forced open so each result is visible.
 */
export function useOrgTreeState(allNodes: OrgTreeNode[]) {
  const [search, setSearch] = useState('')
  const [expanded, setExpanded] = useState<ReadonlySet<string> | null>(null)

  const nodes = useMemo(() => filterTree(allNodes, search), [allNodes, search])
  const expandableKeys = useMemo(() => collectExpandableKeys(nodes), [nodes])
  const defaultExpanded = useMemo<ReadonlySet<string>>(() => new Set(allNodes.map((node) => node.key)), [allNodes])
  const current = expanded ?? defaultExpanded
  const isSearching = search.trim() !== ''

  const collapsedKeys = useMemo(
    () => new Set(expandableKeys.filter((key) => !current.has(key))),
    [expandableKeys, current],
  )

  const toggle = useCallback(
    (key: string) => {
      setExpanded((previous) => {
        const next = new Set(previous ?? defaultExpanded)
        if (!next.delete(key)) next.add(key)
        return next
      })
    },
    [defaultExpanded],
  )

  const expandAll = useCallback(() => setExpanded(new Set(expandableKeys)), [expandableKeys])
  const collapseAll = useCallback(() => setExpanded(new Set()), [])

  return {
    search,
    setSearch,
    nodes,
    isSearching,
    hasExpandable: expandableKeys.length > 0,
    allCollapsed: expandableKeys.length > 0 && collapsedKeys.size === expandableKeys.length,
    collapsedKeys: isSearching ? NO_COLLAPSED_KEYS : collapsedKeys,
    toggle,
    expandAll,
    collapseAll,
  }
}
