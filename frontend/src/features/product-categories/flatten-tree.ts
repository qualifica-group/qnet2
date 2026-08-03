import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** A flattened tree node, ready to feed a plain `id`/`name` select. */
export interface FlatCategoryOption {
  id: number
  name: string
  /**
   * Listed but not pickable. A category the picker may not target still has
   * to be SEEN (user directive 2026-08-03): it is the branch its pickable
   * children hang from, and a list that hides it reads as a flat set of
   * unrelated leaves.
   */
  disabled?: boolean
  /** Nesting level, indented by the select. The name itself stays plain, so the trigger and the search read it undecorated. */
  depth: number
}

/**
 * Sentinel id standing for "no parent" in a category picker: `SearchableSelect`
 * models a selection as a number, so the root option needs a value no real
 * category can have. Shared by the category form and the bulk-move dialog.
 */
export const ROOT_PARENT_VALUE = 0

/**
 * Restricts which nodes may be PICKED (spec 0074 D-6). The tree is the
 * structural channel and stays complete: the parent picker and the bulk-move
 * dialog pass nothing (everything pickable), the destination pickers pass the
 * id set they accept.
 */
export interface FlattenCategoryTreeOptions {
  /**
   * The ids a caller accepts as a selection. Every OTHER node is still listed
   * — disabled — so the hierarchy above a pickable leaf stays visible.
   * Omitted: every node is pickable (the structural pickers).
   */
  pickableIds?: ReadonlySet<number>
  /** Ids pickable regardless: the value already saved on the record being edited (D-3b). */
  keepIds?: readonly number[]
}

/**
 * Flattens the category tree into a depth-first, indented option list for the
 * category pickers (`SearchableSelect`): no dedicated `for-select` endpoint
 * exists for categories (spec 0017 scope), so the already-fetched tree is
 * reused client-side instead of a new backend call.
 *
 * EVERY node becomes an option, in tree order and at the indent of its real
 * depth: a node outside `pickableIds` renders `disabled` rather than being
 * dropped (user directive 2026-08-03), because the parent of a pickable
 * category is what tells the operator WHERE that category lives.
 */
export function flattenCategoryTree(
  nodes: ProductCategoryTreeNode[],
  options: FlattenCategoryTreeOptions = {},
  depth = 0,
): FlatCategoryOption[] {
  const { pickableIds, keepIds } = options

  return nodes.flatMap((node) => {
    const pickable =
      pickableIds === undefined || pickableIds.has(node.id) || (keepIds?.includes(node.id) ?? false)
    const option: FlatCategoryOption = {
      id: node.id,
      name: node.name,
      depth,
      ...(pickable ? {} : { disabled: true }),
    }

    return [option, ...flattenCategoryTree(node.children, options, depth + 1)]
  })
}

/** Every `is_selectable` id in the tree: the pickable set of a destination picker with no further scoping (the product form). */
export function collectSelectableIds(nodes: ProductCategoryTreeNode[]): Set<number> {
  const ids = new Set<number>()

  function visit(candidates: ProductCategoryTreeNode[]): void {
    for (const node of candidates) {
      if (node.is_selectable) {
        ids.add(node.id)
      }
      visit(node.children)
    }
  }

  visit(nodes)
  return ids
}

/**
 * Drops the branches containing no pickable node at all: disabled ancestors
 * are context for what hangs underneath them, and a whole branch that offers
 * nothing is noise, not context. Ancestors of a kept node survive even when
 * they are not pickable themselves.
 */
export function pruneToPickable(
  nodes: ProductCategoryTreeNode[],
  pickableIds: ReadonlySet<number>,
): ProductCategoryTreeNode[] {
  return nodes.flatMap((node) => {
    const children = pruneToPickable(node.children, pickableIds)

    if (!pickableIds.has(node.id) && children.length === 0) {
      return []
    }

    return [{ ...node, children }]
  })
}

/** Collects every descendant id of `nodeId` (inclusive), used to forbid picking a cyclic parent client-side. */
export function collectSubtreeIds(nodes: ProductCategoryTreeNode[], nodeId: number): Set<number> {
  const ids = new Set<number>()

  function visit(candidates: ProductCategoryTreeNode[], collecting: boolean) {
    for (const node of candidates) {
      const isTarget = collecting || node.id === nodeId
      if (isTarget) {
        ids.add(node.id)
      }
      visit(node.children, isTarget)
    }
  }

  visit(nodes, false)
  return ids
}
