import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** A flattened tree node, ready to feed a plain `id`/`name` select. */
export interface FlatCategoryOption {
  id: number
  name: string
}

/**
 * Sentinel id standing for "no parent" in a category picker: `SearchableSelect`
 * models a selection as a number, so the root option needs a value no real
 * category can have. Shared by the category form and the bulk-move dialog.
 */
export const ROOT_PARENT_VALUE = 0

/** Indentation glyph prepended per depth level, so hierarchy reads at a glance in a flat list. */
const DEPTH_PREFIX = '    '

/**
 * Restricts which nodes become OPTIONS (spec 0074 D-6). The tree is the
 * structural channel and stays complete: the parent picker and the bulk-move
 * dialog pass nothing, the product form — a destination served by this same
 * cached tree — passes `selectableOnly`.
 */
export interface FlattenCategoryTreeOptions {
  /** Drop nodes flagged `is_selectable: false` from the options. */
  selectableOnly?: boolean
  /** Ids kept regardless: the value already saved on the record being edited (D-3b). */
  keepIds?: readonly number[]
}

/**
 * Flattens the category tree into a depth-first, indented option list for the
 * product form's category picker (`SearchableSelect`): no dedicated
 * `for-select` endpoint exists for categories (spec 0017 scope), so the
 * already-fetched tree is reused client-side instead of a new backend call.
 *
 * A node filtered out by `selectableOnly` is omitted from the OPTIONS but its
 * children are still walked: hiding a container must never hide the branch
 * underneath it. Depth (and therefore the indent) keeps reflecting the real
 * tree position, not the filtered one.
 */
export function flattenCategoryTree(
  nodes: ProductCategoryTreeNode[],
  options: FlattenCategoryTreeOptions = {},
  depth = 0,
): FlatCategoryOption[] {
  const { selectableOnly = false, keepIds } = options

  return nodes.flatMap((node) => {
    const included =
      !selectableOnly || node.is_selectable || (keepIds?.includes(node.id) ?? false)
    const option = { id: node.id, name: `${DEPTH_PREFIX.repeat(depth)}${depth > 0 ? '↳ ' : ''}${node.name}` }

    return [
      ...(included ? [option] : []),
      ...flattenCategoryTree(node.children, options, depth + 1),
    ]
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
