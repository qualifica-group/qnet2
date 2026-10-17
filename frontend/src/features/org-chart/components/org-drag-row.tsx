import { type ReactNode } from 'react'
import { useDraggable, useDroppable } from '@dnd-kit/core'
import { cn } from '@/lib/utils'
import { resolveDrop } from '@/features/org-chart/move-destinations'
import type { MoveDestination } from '@/features/org-chart/move-types'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'

/** Row being dragged plus the places it may land on. */
export interface OrgDragState {
  node: OrgTreeNode
  destinations: MoveDestination[]
}

interface OrgDragRowProps {
  node: OrgTreeNode
  row: ReactNode
  draggable: boolean
  dragging: OrgDragState | null
}

/**
 * Wraps a tree row as draggable (when movable) and droppable (only while a
 * drag is on and this row is one of its valid destinations). Invalid rows are
 * neither highlighted nor registered as targets. No dnd-kit `attributes`: they
 * would add role/tabindex and break the tree semantics (keyboard path = menu).
 */
export function OrgDragRow({ node, row, draggable, dragging }: OrgDragRowProps) {
  const drag = useDraggable({ id: node.key, data: { node }, disabled: !draggable })
  const isValidTarget =
    dragging !== null && dragging.node.key !== node.key && resolveDrop(dragging.destinations, node) !== null
  const drop = useDroppable({ id: node.key, data: { node }, disabled: !isValidTarget })

  const stateClass = drop.isOver
    ? 'ring-2 ring-ring bg-accent/40'
    : isValidTarget
      ? 'ring-1 ring-border'
      : drag.isDragging
        ? 'opacity-50'
        : ''

  return (
    <div
      ref={(element) => {
        drag.setNodeRef(element)
        drop.setNodeRef(element)
      }}
      {...(draggable ? drag.listeners : undefined)}
      className={cn('rounded-md', stateClass)}
    >
      {row}
    </div>
  )
}
