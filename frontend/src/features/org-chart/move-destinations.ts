import type { MoveDestination, OrgMove, OrgView, PendingMove } from '@/features/org-chart/move-types'
import type { OrgNodeRef, OrgTreeNode } from '@/features/org-chart/tree-nodes'

/**
 * Client-side mirror of the server rules (spec 0273 D-6) used to offer only
 * valid destinations; the server stays the authority and re-validates.
 */

export function nodeName(node: OrgTreeNode): string {
  if (node.kind === 'person') return node.person.name
  if (node.kind === 'function') return node.businessFunction.name
  return ''
}

/** Cheap, per-row precondition: can this row be moved at all in this view? */
export function isMovable(view: OrgView, node: OrgTreeNode): boolean {
  if (node.kind === 'all-functions') return false
  if (node.kind === 'function') return true
  if (view === 'people') return !node.person.is_manager || node.parent !== null
  // A person under "all functions" covers every function: nothing to move.
  return node.parent?.kind === 'function'
}

export function findNode(nodes: OrgTreeNode[], ref: OrgNodeRef): OrgTreeNode | null {
  for (const node of nodes) {
    if (node.kind === ref.kind && node.id === ref.id) return node
    const found = findNode(node.children, ref)
    if (found) return found
  }
  return null
}

function collectIds(node: OrgTreeNode, ids: Set<number>): Set<number> {
  if (node.id !== null) ids.add(node.id)
  node.children.forEach((child) => {
    if (child.kind === node.kind) collectIds(child, ids)
  })
  return ids
}

function candidates(nodes: OrgTreeNode[], kind: 'person' | 'function', excluded: ReadonlySet<number>): MoveDestination[] {
  const seen = new Set<number>()
  const out: MoveDestination[] = []
  const walk = (list: OrgTreeNode[], depth: number) => {
    list.forEach((node) => {
      if (node.kind === kind && node.id !== null && !excluded.has(node.id) && !seen.has(node.id)) {
        seen.add(node.id)
        out.push({ kind, id: node.id, name: nodeName(node), depth })
      }
      walk(node.children.filter((child) => child.kind === kind), depth + 1)
    })
  }
  walk(nodes, 0)
  return out
}

/** Valid destinations of `node` (taken from the unfiltered tree `allNodes`). */
export function getMoveDestinations(view: OrgView, node: OrgTreeNode, allNodes: OrgTreeNode[]): MoveDestination[] {
  if (!isMovable(view, node) || node.kind === 'all-functions' || node.id === null) return []

  const root: MoveDestination = { kind: 'root', id: null, name: '', depth: 0 }
  const fromId = node.parent?.id ?? null

  if (view === 'people') {
    const subject = findNode(allNodes, { kind: 'person', id: node.id }) ?? node
    // A manager can only stop reporting to anyone.
    if (node.kind === 'person' && node.person.is_manager) return node.parent ? [root] : []
    const excluded = collectIds(subject, new Set())
    if (fromId !== null) excluded.add(fromId)
    // Already one of the person's managers: moving there would only duplicate the link.
    if (node.kind === 'person' && 'manager_ids' in node.person) node.person.manager_ids.forEach((id) => excluded.add(id))
    return [...(node.parent ? [root] : []), ...candidates(allNodes, 'person', excluded)]
  }

  if (node.kind === 'person') {
    return candidates(allNodes, 'function', new Set(fromId === null ? [] : [fromId]))
  }
  const subject = findNode(allNodes, { kind: 'function', id: node.id }) ?? node
  const excluded = collectIds(subject, new Set())
  if (fromId !== null) excluded.add(fromId)
  return [...(node.parent ? [root] : []), ...candidates(allNodes, 'function', excluded)]
}

/**
 * Drop rule: the target row must be one of the dragged node's valid
 * destinations. Returns it, or `null` when the drop is not allowed.
 */
export function resolveDrop(
  destinations: readonly MoveDestination[],
  target: OrgTreeNode,
): MoveDestination | null {
  if (target.kind === 'all-functions') return null
  return destinations.find((dest) => dest.kind === target.kind && dest.id === target.id) ?? null
}

/** Shapes the confirmation payload for moving `node` to `destination`. */
export function buildPendingMove(
  view: OrgView,
  node: OrgTreeNode,
  destination: MoveDestination,
  allNodes: OrgTreeNode[],
): PendingMove {
  const fromRef = node.parent
  const fromId = fromRef?.id ?? null
  const fromName = fromRef ? nodeName(findNode(allNodes, fromRef) ?? node) : null
  const toId = destination.id
  const nodeId = node.id as number

  let move: OrgMove
  if (node.kind === 'function') move = { type: 'parent', functionId: nodeId, toId }
  else if (view === 'people') move = { type: 'manager', userId: nodeId, fromId, toId }
  else move = { type: 'function', userId: nodeId, fromId: fromId as number, toId: toId as number }

  return {
    move,
    subjectName: nodeName(node),
    fromName: fromName === '' ? null : fromName,
    toName: destination.kind === 'root' ? null : destination.name,
  }
}
