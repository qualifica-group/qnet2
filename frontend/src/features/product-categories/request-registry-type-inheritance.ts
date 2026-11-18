import { resolveBranchRoot } from '@/features/product-categories/branch-root'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { PersonalDataType } from '@/features/personal-data/types'

/** The Gestione Richieste card type a candidate parent would impose on a category, plus the root it comes from. */
export interface InheritedRequestRegistryType {
  registryType: PersonalDataType
  sourceCategory: { id: number; name: string }
}

/**
 * Resolves the `request_registry_type` a category would inherit under
 * `parentId`: the branch ROOT's value. Null when the category is a root and
 * owns it (spec 0410).
 */
export function resolveInheritedRequestRegistryType(
  nodesById: Map<number, ProductCategoryTreeNode>,
  parentId: number | null,
): InheritedRequestRegistryType | null {
  const root = resolveBranchRoot(nodesById, parentId)

  if (root === null) {
    return null
  }

  return { registryType: root.request_registry_type, sourceCategory: { id: root.id, name: root.name } }
}
