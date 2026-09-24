/**
 * Spec 0154 D-1: client-side tree utilities built on top of the for-select
 * catalog's own `meta.parent_id`/`meta.depth`. No dedicated tree endpoint
 * exists for task categories — the contract only adds those two keys to the
 * existing `GET /api/task-categories/for-select` — so this fetches the
 * catalog once (up to the server's own for-select cap) and derives the
 * ancestor path / descendant set client-side from it.
 */

import { useQuery } from '@tanstack/react-query'
import { fetchTaskCategoriesForSelect } from '@/features/task-categories/for-select-api'

/** Server-side for-select cap (see `FOR_SELECT_PAGE_SIZE` note in `for-select/api.ts`). */
const TREE_FETCH_LIMIT = 100

/** A flattened category node, depth-first ordered exactly as the endpoint returns it. */
export interface TaskCategoryTreeNode {
  id: number
  name: string
  color: string
  icon: string | null
  parentId: number | null
  depth: number
}

/**
 * Best-effort full catalog for the tree picker (D-1). A catalog larger than
 * `TREE_FETCH_LIMIT` degrades gracefully: `taskCategoryPathLabel` and
 * `collectDescendantIds` simply see fewer nodes, never throw — the
 * indentation itself stays correct regardless, since it reads each option's
 * OWN `meta.depth` directly (see `task-classification-section.tsx`).
 */
export function useTaskCategoryTree() {
  return useQuery({
    queryKey: ['task-categories', 'tree'],
    queryFn: async () => {
      const response = await fetchTaskCategoriesForSelect({ limit: TREE_FETCH_LIMIT })
      return response.items.map(
        (item): TaskCategoryTreeNode => ({
          id: item.id,
          name: item.label,
          color: item.meta.color,
          icon: item.meta.icon,
          parentId: item.meta.parent_id,
          depth: item.meta.depth,
        }),
      )
    },
  })
}

/**
 * The full ancestor path of `id` ("Padre / Figlio", D-1), or `null` when `id`
 * is not (yet) in `nodes` — the caller falls back to the option's own label.
 */
export function taskCategoryPathLabel(nodes: TaskCategoryTreeNode[], id: number): string | null {
  const byId = new Map(nodes.map((node) => [node.id, node]))
  const names: string[] = []
  let current = byId.get(id)
  const visited = new Set<number>()

  while (current && !visited.has(current.id)) {
    visited.add(current.id)
    names.unshift(current.name)
    current = current.parentId === null ? undefined : byId.get(current.parentId)
  }

  return names.length > 0 ? names.join(' / ') : null
}

/**
 * Every descendant of `id` (exclusive) — used by the admin parent picker
 * (`TaskCategoryFormBody`) to disable the branch under the category being
 * edited client-side (UX only; the server's own cycle guard, spec 0154 D-1,
 * is the actual defense on submit).
 */
export function collectDescendantIds(nodes: TaskCategoryTreeNode[], id: number): Set<number> {
  const childrenOf = new Map<number, number[]>()
  for (const node of nodes) {
    if (node.parentId !== null) {
      childrenOf.set(node.parentId, [...(childrenOf.get(node.parentId) ?? []), node.id])
    }
  }

  const descendants = new Set<number>()
  const stack = [id]
  while (stack.length > 0) {
    const current = stack.pop() as number
    for (const childId of childrenOf.get(current) ?? []) {
      if (!descendants.has(childId)) {
        descendants.add(childId)
        stack.push(childId)
      }
    }
  }
  return descendants
}
