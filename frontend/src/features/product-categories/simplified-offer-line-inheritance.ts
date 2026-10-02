import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Mirrors the backend walk-depth guard: defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/** The simplified-offer-line value a candidate parent passes on, plus the nearest ancestor declaring it. */
export interface InheritedSimplifiedOfferLine {
  value: boolean
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `simplified_offer_line` value a category would inherit under
 * `parentId` (spec 0188): the parent's EFFECTIVE value, declared by the
 * nearest ancestor (walking up from the parent) with a non-null override,
 * else by the root. Null when there is nothing to inherit — the category is a
 * root and declares the value itself — or the parent is not in the tree.
 * Client mirror of the backend `SimplifiedOfferLineInheritance`.
 */
export function resolveInheritedSimplifiedOfferLine(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedSimplifiedOfferLine | null {
  const parent = parentId !== null ? nodesById.get(parentId) : undefined
  if (parent === undefined) {
    return null
  }

  let node: ProductCategoryTreeNode | undefined = parent
  let depth = 0

  while (node !== undefined && depth < MAX_DEPTH) {
    if (node.simplified_offer_line_override !== null || node.parent_id === null) {
      return { value: parent.simplified_offer_line, sourceCategory: { id: node.id, name: node.name } }
    }
    node = nodesById.get(node.parent_id)
    depth += 1
  }

  return null
}

/**
 * The override to store when the operator sets the switch to `checked`:
 * matching the inherited value goes back to inheriting (null), anything else
 * is forced.
 */
export function simplifiedOfferLineOverrideFor(
  checked: boolean,
  inherited: InheritedSimplifiedOfferLine,
): boolean | null {
  return checked === inherited.value ? null : checked
}
