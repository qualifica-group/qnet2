import { chartChildren, type OrgTreeNode } from '@/features/org-chart/tree-nodes'

export interface VisibleEntry {
  node: OrgTreeNode
  parentKey: string | null
}

/**
 * Keeps the nodes whose own text matches (with their whole subtree) and the
 * ancestors of any match, so every branch with a result stays reachable.
 */
export function filterTree(nodes: OrgTreeNode[], search: string): OrgTreeNode[] {
  const needle = search.trim().toLowerCase()
  if (!needle) return nodes
  const walk = (list: OrgTreeNode[]): OrgTreeNode[] =>
    list.flatMap((node) => {
      if (node.searchText.includes(needle)) return [node]
      const children = walk(node.children)
      return children.length > 0 ? [{ ...node, children }] : []
    })
  return walk(nodes)
}

/** Keys of every card with child cards: the expand/collapse-all universe. */
export function collectExpandableKeys(nodes: OrgTreeNode[]): string[] {
  return nodes.flatMap((node) => {
    const children = chartChildren(node)
    return children.length > 0 ? [node.key, ...collectExpandableKeys(children)] : []
  })
}

/** Pre-order list of the cards currently on screen, for keyboard navigation. */
export function flattenVisible(
  nodes: OrgTreeNode[],
  collapsedKeys: ReadonlySet<string>,
  parentKey: string | null = null,
): VisibleEntry[] {
  return nodes.flatMap((node) => [
    { node, parentKey },
    ...(collapsedKeys.has(node.key) ? [] : flattenVisible(chartChildren(node), collapsedKeys, node.key)),
  ])
}

export function findByKey(nodes: OrgTreeNode[], key: string): OrgTreeNode | null {
  for (const node of nodes) {
    if (node.key === key) return node
    const found = findByKey(node.children, key)
    if (found) return found
  }
  return null
}
