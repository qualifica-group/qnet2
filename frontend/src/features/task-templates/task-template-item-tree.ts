import type { TaskTemplateItem, TaskTemplateItemFormRow } from '@/features/task-templates/types'

/**
 * Parent-chain tree helpers (spec 0172 D-1: sub-tasks up to 3 levels below a
 * root item — child, grandchild, great-grandchild). Shared, pure logic
 * between the item editor's local rows (`TaskTemplateItemFormRow`, string
 * `id`/`parent_key`) and the read-only detail's persisted rows
 * (`TaskTemplateItem`, numeric `id`/`parent_id`) — the only two places that
 * derive nesting depth from a flat, parent-before-child ordered list
 * (data_contract: "items piatti ordinati per sort_order, padre sempre prima
 * dei figli").
 */

/** A root item (depth 0) may nest a child/grandchild/great-grandchild — a 4th level below root is rejected server-side (AC-003). */
export const MAX_ITEM_DEPTH = 3

export interface TaskTemplateTreeAccessors<T> {
  idOf: (node: T) => string
  parentIdOf: (node: T) => string | null
}

export const FORM_ROW_TREE_ACCESSORS: TaskTemplateTreeAccessors<TaskTemplateItemFormRow> = {
  idOf: (row) => row.id,
  parentIdOf: (row) => row.parent_key,
}

export const PERSISTED_ITEM_TREE_ACCESSORS: TaskTemplateTreeAccessors<TaskTemplateItem> = {
  idOf: (item) => String(item.id),
  parentIdOf: (item) => (item.parent_id === null ? null : String(item.parent_id)),
}

/** Depth of every node, root = 0. Assumes a node's parent already occupies an earlier index. */
export function computeItemDepths<T>(nodes: T[], { idOf, parentIdOf }: TaskTemplateTreeAccessors<T>): Map<string, number> {
  const depthById = new Map<string, number>()
  for (const node of nodes) {
    const parentId = parentIdOf(node)
    const parentDepth = parentId !== null ? (depthById.get(parentId) ?? 0) : -1
    depthById.set(idOf(node), parentDepth + 1)
  }
  return depthById
}

/** A row may gain a subtask only under the cap — its new child would land one level deeper (AC-018: absent at the 3rd level). */
export function canAddSubtaskAtDepth(depth: number): boolean {
  return depth < MAX_ITEM_DEPTH
}

/** `rootId` and every one of its descendants (any depth), depth-first, siblings kept in the array's own relative order. */
export function getSubtreeIds<T>(
  nodes: T[],
  { idOf, parentIdOf }: TaskTemplateTreeAccessors<T>,
  rootId: string,
): string[] {
  const childrenByParentId = new Map<string, string[]>()
  for (const node of nodes) {
    const parentId = parentIdOf(node)
    if (parentId === null) {
      continue
    }
    childrenByParentId.set(parentId, [...(childrenByParentId.get(parentId) ?? []), idOf(node)])
  }

  const result: string[] = [rootId]
  const collectDescendants = (id: string) => {
    for (const childId of childrenByParentId.get(id) ?? []) {
      result.push(childId)
      collectDescendants(childId)
    }
  }
  collectDescendants(rootId)
  return result
}
