/**
 * Scoping of the product-category TREE for a product-line row (user directive
 * 2026-08-03): the row's category picker now reads the structural tree — the
 * same cached `/product-categories/tree` the product form reads — instead of
 * the flat `for-select` page, so the operator sees the parent categories a
 * pickable one hangs from.
 *
 * Everything the for-select resolved server-side is resolved here against the
 * same data, with the same rules:
 *  - the EFFECTIVE business function (own-or-inherited, spec 0040 BR-4) —
 *    `ProductCategoryTreeNode.business_function_id` is the node's OWN one, so
 *    the inheritance is walked here;
 *  - the branch-root subtree (spec 0077 INV-1, the for-select's
 *    `root_category_id` param);
 *  - the branch root's `management_mode` (spec 0077), which the for-select
 *    used to hand over as `meta` on the picked item.
 */
import type { CategoryManagementMeta } from '@/features/product-lines/management-mode'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * The ids a row whose business function is `businessFunctionId` may actually
 * pick: `is_selectable` AND effective business function matching. Every other
 * node stays visible as disabled context (see `flattenCategoryTree`), which is
 * the whole point of reading the tree here.
 */
export function pickableCategoryIdsFor(
  nodes: ProductCategoryTreeNode[],
  businessFunctionId: number,
): Set<number> {
  const ids = new Set<number>()

  function visit(candidates: ProductCategoryTreeNode[], inheritedFunctionId: number | null): void {
    for (const node of candidates) {
      const effectiveFunctionId = node.business_function_id ?? inheritedFunctionId
      if (node.is_selectable && effectiveFunctionId === businessFunctionId) {
        ids.add(node.id)
      }
      visit(node.children, effectiveFunctionId)
    }
  }

  visit(nodes, null)
  return ids
}

/** The subtree rooted at `rootCategoryId` (the root included), or `[]` when that id is not in the tree. */
export function subtreeOf(
  nodes: ProductCategoryTreeNode[],
  rootCategoryId: number,
): ProductCategoryTreeNode[] {
  for (const node of nodes) {
    if (node.id === rootCategoryId) {
      return [node]
    }
    const found = subtreeOf(node.children, rootCategoryId)
    if (found.length > 0) {
      return found
    }
  }

  return []
}

/**
 * The picked category's branch root id + its EFFECTIVE management mode — what
 * the for-select item used to carry as `meta.root_category_id` /
 * `meta.management_mode`. `management_mode` is already mirrored on every
 * descendant server-side, so it is read off the node itself; only the root id
 * needs the walk.
 */
export function categoryManagementMetaFor(
  nodes: ProductCategoryTreeNode[],
  categoryId: number,
): CategoryManagementMeta | null {
  for (const root of nodes) {
    const found = findNode([root], categoryId)
    if (found !== null) {
      return { rootCategoryId: root.id, managementMode: found.management_mode }
    }
  }

  return null
}

/**
 * The row set's resolved policy: the meta of the first row (in order) whose
 * category is found in the tree. `null` only while the tree has not loaded or
 * no row carries a category yet — the indeterminate case, left unconstrained.
 *
 * Resolved from the tree and NOT from what the operator picked in this
 * session (user directive 2026-08-05): a row hydrated on edit — the
 * opportunity form, the request work panel — carries the same policy as one
 * just picked, so the mode is enforced there exactly as in the create forms.
 * Server-side D-5 grandfathering is untouched: a historic non-conforming
 * record still saves as long as its rows are not resubmitted.
 */
export function resolveRowSetManagementMode(
  rows: ProductLineRow[],
  nodes: ProductCategoryTreeNode[],
): CategoryManagementMeta | null {
  for (const row of rows) {
    if (row.product_category_id !== null) {
      const meta = categoryManagementMetaFor(nodes, row.product_category_id)
      if (meta !== null) {
        return meta
      }
    }
  }

  return null
}

/** Depth-first lookup by id. */
function findNode(
  nodes: ProductCategoryTreeNode[],
  categoryId: number,
): ProductCategoryTreeNode | null {
  for (const node of nodes) {
    if (node.id === categoryId) {
      return node
    }
    const found = findNode(node.children, categoryId)
    if (found !== null) {
      return found
    }
  }

  return null
}
