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
 *
 * Spec 0132 reintroduces a root id, but for a DIFFERENT purpose: the card
 * row's own two-step pick (root category, then one of its descendants),
 * unrelated to the retired INV-1 branch confinement — see
 * `selectableIdsUnderRoot`/`rootCategoryIdFor` below.
 */
import { collectSelectableIds } from '@/features/product-categories/flatten-tree'
import type { CategoryManagementMode, ProductCategoryTreeNode } from '@/features/product-categories/types'

export interface PickableCategoryOptions {
  /**
   * Spec 0129 D-6/D-7: also admit a container category (`is_selectable=false`)
   * whose EFFECTIVE function matches `businessFunctionId`, or a functionless
   * ("neutral") container with at least one descendant under that function.
   * Used only by `CompetenceLinesField` — offers/projects/campaigns/requests
   * keep the default (`is_selectable` mandatory, spec 0111 D-4, unchanged).
   */
  includeContainers?: boolean
}

/**
 * The ids a row whose business function is `businessFunctionId` may actually
 * pick. Default: `is_selectable` AND effective business function matching
 * (spec 0111 D-4). With `includeContainers` (spec 0129 D-7): effective
 * function matching regardless of `is_selectable`, OR a functionless node
 * with at least one descendant whose effective function is
 * `businessFunctionId`. Every other node stays visible as disabled context
 * (see `flattenCategoryTree`), which is the whole point of reading the tree
 * here.
 */
export function pickableCategoryIdsFor(
  nodes: ProductCategoryTreeNode[],
  businessFunctionId: number,
  options: PickableCategoryOptions = {},
): Set<number> {
  const { includeContainers = false } = options
  const ids = new Set<number>()

  // D-7: whether some descendant of `node` has EFFECTIVE function
  // `businessFunctionId` — the test for a "neutral" container (no function of
  // its own) that still gathers a branch of the row's function.
  function descendantHasFunction(node: ProductCategoryTreeNode, inheritedFunctionId: number | null): boolean {
    return node.children.some((child) => {
      const childEffectiveFunctionId = child.business_function_id ?? inheritedFunctionId
      return childEffectiveFunctionId === businessFunctionId || descendantHasFunction(child, childEffectiveFunctionId)
    })
  }

  function visit(candidates: ProductCategoryTreeNode[], inheritedFunctionId: number | null): void {
    for (const node of candidates) {
      const effectiveFunctionId = node.business_function_id ?? inheritedFunctionId
      const matchesFunction = effectiveFunctionId === businessFunctionId
      const admits = includeContainers
        ? matchesFunction || (effectiveFunctionId === null && descendantHasFunction(node, effectiveFunctionId))
        : node.is_selectable && matchesFunction
      if (admits) {
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
 * The row set's resolved policy, from the categories its rows carry. Takes
 * only the slice it actually reads — `product_category_id` — rather than the
 * full `ProductLineRow` shape, so it stays usable by every row shape that
 * carries one: the card row (spec 0132), the inline cell editor's own pair
 * type, and (were it ever needed) a competence row.
 *
 * Resolved from the tree and NOT from what the operator picked in this
 * session (user directive 2026-08-05): a row hydrated on edit — the
 * opportunity form, the request work panel — carries the same policy as one
 * just picked, so the mode is enforced there exactly as in the create forms.
 * Server-side D-5 grandfathering is untouched: a historic non-conforming
 * record still saves as long as its rows are not resubmitted.
 */
export function resolveRowSetManagementMode(
  rows: readonly { product_category_id: number | null }[],
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

/**
 * The ids a card row scoped to `rootCategoryId` may pick (spec 0132 D-1/D-2):
 * every `is_selectable` node in that root's own subtree, root included,
 * WITHOUT any business-function constraint — the function is derived
 * server-side from whichever leaf the operator lands on, not filtered here.
 * An unknown root resolves to an empty set (nothing pickable yet).
 */
export function selectableIdsUnderRoot(
  nodes: ProductCategoryTreeNode[],
  rootCategoryId: number,
): Set<number> {
  const root = findNode(nodes, rootCategoryId)
  return root === null ? new Set<number>() : collectSelectableIds([root])
}

/**
 * Walks the tree from a persisted category up to its ROOT ancestor (spec 0132
 * AC-017): the id an edit-loaded row's `root_category_id` preselects, off the
 * same cached tree — no request of its own. A category that IS a root
 * resolves to itself, matching D-5 ("una categoria che e' essa stessa
 * radice"). `null` when the id is not found (tree not loaded yet, or the id
 * does not exist in it).
 */
export function rootCategoryIdFor(nodes: ProductCategoryTreeNode[], categoryId: number): number | null {
  function search(candidates: ProductCategoryTreeNode[], rootId: number): number | null {
    for (const node of candidates) {
      if (node.id === categoryId) {
        return rootId
      }
      const found = search(node.children, rootId)
      if (found !== null) {
        return found
      }
    }
    return null
  }

  for (const root of nodes) {
    const found = search([root], root.id)
    if (found !== null) {
      return found
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
