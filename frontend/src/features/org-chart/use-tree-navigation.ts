import { useMemo, useRef, useState, type KeyboardEvent, type FocusEvent } from 'react'
import { chartChildren, type OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { flattenVisible } from '@/features/org-chart/tree-utils'

interface TreeNavigationParams {
  nodes: OrgTreeNode[]
  collapsedKeys: ReadonlySet<string>
  onToggle: (key: string) => void
}

/** Selector of the card's primary action (name link / button), activated by Enter. */
const PRIMARY_ACTION_SELECTOR = '[data-org-content] a, [data-org-content] button'

/**
 * WAI-ARIA tree keyboard model with a roving tabindex: Up/Down move through
 * the visible cards (pre-order), Right expands or enters the branch, Left collapses or goes
 * to the parent, Home/End jump to the ends, Enter opens the card's link. Keys
 * are handled only when the `treeitem` itself holds focus, so links and
 * buttons inside a card keep their native behaviour.
 */
export function useTreeNavigation({ nodes, collapsedKeys, onToggle }: TreeNavigationParams) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [focusedKey, setFocusedKey] = useState<string | null>(null)
  const visible = useMemo(() => flattenVisible(nodes, collapsedKeys), [nodes, collapsedKeys])

  const activeKey =
    focusedKey !== null && visible.some((entry) => entry.node.key === focusedKey)
      ? focusedKey
      : (visible[0]?.node.key ?? null)

  function focusNode(key: string | undefined): void {
    if (key === undefined) return
    const items = containerRef.current?.querySelectorAll<HTMLElement>('[role="treeitem"]') ?? []
    Array.from(items)
      .find((item) => item.dataset.nodeKey === key)
      ?.focus()
  }

  function onItemFocus(event: FocusEvent<HTMLElement>): void {
    if (event.target === event.currentTarget) setFocusedKey(event.currentTarget.dataset.nodeKey ?? null)
  }

  function onItemKeyDown(event: KeyboardEvent<HTMLElement>): void {
    if (event.target !== event.currentTarget) return
    const key = event.currentTarget.dataset.nodeKey
    const index = visible.findIndex((entry) => entry.node.key === key)
    if (index < 0) return
    const { node, parentKey } = visible[index]
    const hasChildren = chartChildren(node).length > 0
    const expanded = hasChildren && !collapsedKeys.has(node.key)

    const handlers: Record<string, () => void> = {
      ArrowDown: () => focusNode(visible[index + 1]?.node.key),
      ArrowUp: () => focusNode(visible[index - 1]?.node.key),
      Home: () => focusNode(visible[0]?.node.key),
      End: () => focusNode(visible.at(-1)?.node.key),
      ArrowRight: () => {
        if (!hasChildren) return
        if (expanded) focusNode(visible[index + 1]?.node.key)
        else onToggle(node.key)
      },
      ArrowLeft: () => {
        if (expanded) onToggle(node.key)
        else if (parentKey !== null) focusNode(parentKey)
      },
      Enter: () => event.currentTarget.querySelector<HTMLElement>(PRIMARY_ACTION_SELECTOR)?.click(),
    }
    const handler = handlers[event.key]
    if (handler === undefined) return
    event.preventDefault()
    handler()
  }

  return { containerRef, activeKey, onItemFocus, onItemKeyDown }
}
