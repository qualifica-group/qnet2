import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Mirrors the backend `ReportableInheritance` walk-depth guard: defends against a malformed/cyclic tree. */
const MAX_DEPTH = 100

/** The report flag a candidate parent would pass on, plus the ancestor carrying it (null: nothing up the chain sets it). */
export interface InheritedReportable {
  value: boolean
  sourceCategory: { id: number; name: string } | null
}

/**
 * Resolves the report flag a category would inherit under `parentId` (user
 * directive 2026-09-18): the nearest ancestor's OWN `is_reportable` override,
 * false when none sets it. Null when `parentId` is null — a root inherits
 * nothing. Client mirror of the backend `ReportableInheritance`.
 */
export function resolveInheritedReportable(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedReportable | null {
  if (parentId === null) {
    return null
  }

  let currentId: number | null = parentId
  let depth = 0

  while (currentId !== null && depth < MAX_DEPTH) {
    const node = nodesById.get(currentId)
    if (!node) {
      break
    }
    if (node.is_reportable !== null) {
      return { value: node.is_reportable, sourceCategory: { id: node.id, name: node.name } }
    }
    currentId = node.parent_id
    depth += 1
  }

  return { value: false, sourceCategory: null }
}

/**
 * The override to store when the operator sets the switch to `checked`:
 * matching the inherited value goes back to inheriting (null), anything else
 * is forced. A root inherits "not reportable", so switching it off stores null.
 */
export function reportableOverrideFor(checked: boolean, inherited: InheritedReportable | null): boolean | null {
  return checked === (inherited?.value ?? false) ? null : checked
}
