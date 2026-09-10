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
 *  - the branch root's `management_mode` (spec 0077), which the for-select
 *    used to hand over as `meta` on the picked item.
 *
 * Spec 0077 rev.2 (user directive 2026-08-31) revoked INV-1/INV-2: rows are
 * independent, so nothing scopes a picker to a branch root any more and the
 * root id itself has no consumer left — only the mode survives.
 */
import type { CategoryManagementMode, ProductCategoryTreeNode } from '@/features/product-categories/types'
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

/**
 * The management mode governing a set of categories: the STRICTEST one wins
 * (spec 0077 rev.2, D-10) — one category on a `single` root governs the whole
 * card, whatever the others resolve to. `null` only while the tree has not
 * loaded or no id resolves in it: the indeterminate case, left unconstrained
 * (spec 0077 point 4).
 *
 * `management_mode` is already mirrored on every descendant server-side, so
 * it is read off the node itself — no root walk needed.
 */
export function resolveManagementMode(
  nodes: ProductCategoryTreeNode[],
  categoryIds: number[],
): CategoryManagementMode | null {
  let resolved: CategoryManagementMode | null = null

  for (const categoryId of categoryIds) {
    const mode = findNode(nodes, categoryId)?.management_mode ?? null
    if (mode === 'single') {
      return 'single'
    }
    resolved ??= mode
  }

  return resolved
}

/**
 * The row set's resolved policy, from the categories its rows carry.
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
): CategoryManagementMode | null {
  return resolveManagementMode(
    nodes,
    rows.map((row) => row.product_category_id).filter((id): id is number => id !== null),
  )
}

/**
 * Whether the row set's classification is under the simplified-offer-line
 * rule (spec 0114): a single simplified category is enough for the whole
 * card — the LOOSEST of the covered categories wins, the opposite bias from
 * `resolveManagementMode`'s strictest-wins, since simplification is a UI
 * relief the operator gets as soon as ONE of the covered branches grants it.
 * `simplified_offer_line` is already mirrored on every descendant
 * server-side, so it is read off the node itself — no root walk needed.
 */
export function resolveSimplifiedOfferLine(
  nodes: ProductCategoryTreeNode[],
  categoryIds: number[],
): boolean {
  return categoryIds.some((categoryId) => findNode(nodes, categoryId)?.simplified_offer_line === true)
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
