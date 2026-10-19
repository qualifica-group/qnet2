import { chartChildren, type OrgTreeNode } from '@/features/org-chart/tree-nodes'

/**
 * Card geometry of the graphic org chart (spec 0274 D-4). Cards have a fixed
 * size so the layout and the connector curves are computed without measuring
 * the DOM.
 */
export const CARD_WIDTH = 208
export const CARD_HEIGHT = 128
/** Distance between a card's bottom edge and the centre of its expand/collapse pill. */
export const TOGGLE_OFFSET = 18
const SIBLING_GAP = 20
const ROOT_GAP = 40
const LEVEL_GAP = 72
const STACK_GAP = 20
const CHART_PADDING = 24
/** Roots wrap onto a new shelf past this width, so dozens of unconnected people do not form one endless row. */
const MAX_SHELF_WIDTH = 1240
/** More leaf children than this are stacked in a grid of `LEAF_COLUMNS` columns (spec 0274 D-4). */
const LEAF_GRID_THRESHOLD = 4
const LEAF_COLUMNS = 4

export interface ChartCard {
  node: OrgTreeNode
  x: number
  y: number
  /** 1-based tree level, set size and position for the flat `treeitem` ARIA model. */
  level: number
  setSize: number
  posInSet: number
  parentKey: string | null
  /** Card right above in a leaf grid column: joined by a straight line instead of a curve from the parent. */
  stackedOnKey: string | null
  hasChildren: boolean
  expanded: boolean
  /** Cards hidden below a collapsed card (the "+N" of the pill); 0 when expanded. */
  hiddenCount: number
}

export interface ChartEdge {
  key: string
  path: string
}

export interface ChartLayout {
  cards: ChartCard[]
  edges: ChartEdge[]
  width: number
  height: number
}

function countCards(node: OrgTreeNode): number {
  return chartChildren(node).reduce((sum, child) => sum + 1 + countCards(child), 0)
}

function centerX(card: ChartCard): number {
  return card.x + CARD_WIDTH / 2
}

function isLeafGrid(children: OrgTreeNode[]): boolean {
  return children.length > LEAF_GRID_THRESHOLD && children.every((child) => chartChildren(child).length === 0)
}

/** Stem from each expanded card to its pill, a curve from the pill to each child, a line between stacked cards. */
function buildEdges(cards: ChartCard[]): ChartEdge[] {
  const byKey = new Map(cards.map((card) => [card.node.key, card]))
  return cards.flatMap((card): ChartEdge[] => {
    const edges: ChartEdge[] = []
    if (card.expanded) {
      const x = centerX(card)
      edges.push({ key: `${card.node.key}>`, path: `M ${x} ${card.y + CARD_HEIGHT} V ${card.y + CARD_HEIGHT + TOGGLE_OFFSET}` })
    }
    const above = card.stackedOnKey === null ? undefined : byKey.get(card.stackedOnKey)
    const parent = card.parentKey === null ? undefined : byKey.get(card.parentKey)
    if (above) {
      edges.push({ key: `${above.node.key}|${card.node.key}`, path: `M ${centerX(card)} ${above.y + CARD_HEIGHT} V ${card.y}` })
    } else if (parent) {
      const x1 = centerX(parent)
      const y1 = parent.y + CARD_HEIGHT + TOGGLE_OFFSET
      const x2 = centerX(card)
      const midY = (y1 + card.y) / 2
      edges.push({ key: `${parent.node.key}>${card.node.key}`, path: `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${card.y}` })
    }
    return edges
  })
}

/**
 * Top-down tidy layout: children sit one level below their parent, which is
 * centred over its first row of children; many leaf children are stacked in a
 * grid; roots are laid side by side and wrap onto shelves. A subtree only ever
 * occupies the horizontal band it returns, so subtrees never overlap. Cards
 * come out in pre-order (tab / reading order).
 */
export function layoutChart(roots: OrgTreeNode[], collapsedKeys: ReadonlySet<string>): ChartLayout {
  const cards: ChartCard[] = []

  /** Places `node` with its top-left at (left, top) and returns the right edge of its band. */
  function place(node: OrgTreeNode, level: number, left: number, top: number, parentKey: string | null, posInSet: number, setSize: number): number {
    const children = chartChildren(node)
    const expanded = children.length > 0 && !collapsedKeys.has(node.key)
    const card: ChartCard = {
      node,
      x: left,
      y: top,
      level,
      setSize,
      posInSet,
      parentKey,
      stackedOnKey: null,
      hasChildren: children.length > 0,
      expanded,
      hiddenCount: expanded ? 0 : countCards(node),
    }
    cards.push(card)
    if (!expanded) return left + CARD_WIDTH

    const childTop = top + CARD_HEIGHT + LEVEL_GAP
    const firstRow: ChartCard[] = []
    let right: number
    if (isLeafGrid(children)) {
      const columns = Math.min(LEAF_COLUMNS, children.length)
      children.forEach((child, index) => {
        const column = index % columns
        const row = Math.floor(index / columns)
        place(child, level + 1, left + column * (CARD_WIDTH + SIBLING_GAP), childTop + row * (CARD_HEIGHT + STACK_GAP), node.key, index + 1, children.length)
        const childCard = cards[cards.length - 1]
        if (row === 0) firstRow.push(childCard)
        else childCard.stackedOnKey = children[index - columns].key
      })
      right = left + columns * CARD_WIDTH + (columns - 1) * SIBLING_GAP
    } else {
      let cursor = left
      children.forEach((child, index) => {
        const start = cards.length
        cursor = place(child, level + 1, cursor, childTop, node.key, index + 1, children.length) + SIBLING_GAP
        firstRow.push(cards[start])
      })
      right = cursor - SIBLING_GAP
    }
    card.x = (centerX(firstRow[0]) + centerX(firstRow[firstRow.length - 1])) / 2 - CARD_WIDTH / 2
    return right
  }

  // Step 1: lay the roots side by side, moving a subtree to a new shelf when it would overflow the current one
  let cursor = CHART_PADDING
  let shelfTop = CHART_PADDING
  let shelfBottom = CHART_PADDING
  roots.forEach((root, index) => {
    const start = cards.length
    let right = place(root, 1, cursor, shelfTop, null, index + 1, roots.length)
    if (right > MAX_SHELF_WIDTH && cursor > CHART_PADDING) {
      const dx = CHART_PADDING - cursor
      const dy = shelfBottom + LEVEL_GAP - shelfTop
      cards.slice(start).forEach((card) => {
        card.x += dx
        card.y += dy
      })
      right += dx
      shelfTop += dy
    }
    shelfBottom = Math.max(shelfBottom, ...cards.slice(start).map((card) => card.y + CARD_HEIGHT))
    cursor = right + ROOT_GAP
  })

  // Step 2: connectors from the final positions
  const maxRight = Math.max(0, ...cards.map((card) => card.x + CARD_WIDTH))
  return {
    cards,
    edges: buildEdges(cards),
    width: cards.length > 0 ? maxRight + CHART_PADDING : 0,
    height: cards.length > 0 ? shelfBottom + LEVEL_GAP / 2 : 0,
  }
}
