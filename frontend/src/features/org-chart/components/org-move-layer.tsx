import { useState, type ReactNode } from 'react'
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { useAbilities } from '@/features/auth/use-abilities'
import { OrgDragRow, type OrgDragState } from '@/features/org-chart/components/org-drag-row'
import { OrgMoveDialog } from '@/features/org-chart/components/org-move-dialog'
import { OrgRowMenu } from '@/features/org-chart/components/org-row-menu'
import type { OrgRowRenderers } from '@/features/org-chart/components/org-tree'
import { getMoveDestinations, isMovable, nodeName, resolveDrop } from '@/features/org-chart/move-destinations'
import type { MoveDestination, OrgView } from '@/features/org-chart/move-types'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { useFinePointer } from '@/features/org-chart/use-fine-pointer'

/** Pixels the pointer travels before a press becomes a drag, so plain clicks (links, toggles) keep working. */
const DRAG_ACTIVATION_DISTANCE = 6
const PERMISSION_MOVE_PERSON = 'users.update'
const PERMISSION_MOVE_FUNCTION = 'business-functions.update'

interface MoveRequest {
  node: OrgTreeNode
  destination: MoveDestination | null
}

interface OrgMoveLayerProps {
  view: OrgView
  /** The unfiltered tree: destinations and descendants are computed on it, not on the searched subset. */
  nodes: OrgTreeNode[]
  children: (renderers: OrgRowRenderers) => ReactNode
}

function draggedNode(event: { active: { data: { current?: Record<string, unknown> } } }): OrgTreeNode | null {
  return (event.active.data.current?.node as OrgTreeNode | undefined) ?? null
}

/**
 * Move feature of the org chart (spec 0273 D-4/D-5): provides the row menu
 * ("Sposta in…") and, on fine pointers, drag and drop. Both end in the same
 * confirmation dialog and the same mutation. The UI only hides what the user
 * may not do; the server authorizes and validates.
 */
export function OrgMoveLayer({ view, nodes, children }: OrgMoveLayerProps) {
  const { can } = useAbilities()
  const finePointer = useFinePointer()
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: DRAG_ACTIVATION_DISTANCE } }))
  const [request, setRequest] = useState<MoveRequest | null>(null)
  const [dragging, setDragging] = useState<OrgDragState | null>(null)

  function canMove(node: OrgTreeNode): boolean {
    const permission = node.kind === 'function' ? PERMISSION_MOVE_FUNCTION : PERMISSION_MOVE_PERSON
    return can(permission) && isMovable(view, node)
  }

  function onDragStart(event: DragStartEvent): void {
    const node = draggedNode(event)
    if (node) setDragging({ node, destinations: getMoveDestinations(view, node, nodes) })
  }

  function onDragEnd(event: DragEndEvent): void {
    // Step 1: the drop target must be one of the dragged row's valid destinations
    const target = (event.over?.data.current?.node as OrgTreeNode | undefined) ?? null
    const destination = dragging && target ? resolveDrop(dragging.destinations, target) : null
    // Step 2: hand over to the same confirmation the menu uses
    if (dragging && destination) setRequest({ node: dragging.node, destination })
    setDragging(null)
  }

  const renderers: OrgRowRenderers = {
    renderRowActions: (node) =>
      canMove(node) ? <OrgRowMenu name={nodeName(node)} onMove={() => setRequest({ node, destination: null })} /> : null,
    wrapRow: (node, row) => (
      <OrgDragRow node={node} row={row} draggable={finePointer && canMove(node)} dragging={dragging} />
    ),
  }

  return (
    <DndContext sensors={sensors} onDragStart={onDragStart} onDragEnd={onDragEnd} onDragCancel={() => setDragging(null)}>
      {children(renderers)}
      <DragOverlay>
        {dragging ? (
          <div className="rounded-md border border-border bg-card px-2 py-1 text-sm shadow-sm">{nodeName(dragging.node)}</div>
        ) : null}
      </DragOverlay>
      {request ? (
        <OrgMoveDialog
          view={view}
          node={request.node}
          allNodes={nodes}
          initialDestination={request.destination}
          onClose={() => setRequest(null)}
        />
      ) : null}
    </DndContext>
  )
}
