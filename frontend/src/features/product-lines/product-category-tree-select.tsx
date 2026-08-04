import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  flattenCategoryTree,
  pruneToPickable,
} from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import {
  categoryManagementMetaFor,
  pickableCategoryIdsFor,
  subtreeOf,
} from '@/features/product-lines/category-tree-scope'
import type { CategoryManagementMeta } from '@/features/product-lines/management-mode'

export interface ProductCategoryTreeSelectProps {
  value: number | null
  /** Fired with the picked id and the meta resolved from the tree (spec 0077: branch root + management mode). */
  onChange: (categoryId: number, meta: CategoryManagementMeta | null) => void
  /**
   * The row's business function: only the categories whose EFFECTIVE one
   * matches are pickable. `null` (no function chosen yet) offers nothing and
   * disables the control — the row's two steps stay in order.
   */
  businessFunctionId: number | null
  /** Spec 0077 INV-1: rows after the first are confined to the branch root already resolved for the card. */
  rootCategoryId?: number | null
  disabled?: boolean
  /** Quick-create affordance rendered next to the trigger (spec 0028). */
  action?: ReactNode
  /** Accessible name of the trigger — a repeated row editor has no visible label of its own. */
  triggerLabel: string
}

/**
 * The product-line row's "categoria prodotto" picker, reading the structural
 * category TREE (user directive 2026-08-03) so the PARENT categories are
 * listed above the pickable ones, indented, exactly as the product form's
 * picker lists them. A category the row may not target — not `is_selectable`,
 * or belonging to another business function — is shown DISABLED, never
 * hidden: it is the branch its children hang from.
 *
 * This replaces the flat, server-paginated `for-select` list this select used
 * to read. The scoping the endpoint did (effective business function, spec
 * 0077 root subtree) is resolved client-side against the same cached tree the
 * product form and the category tree view already share — see
 * `category-tree-scope.ts` — and so is the picked item's `meta`, which the
 * caller feeds back into the management-mode resolution.
 *
 * Branches offering nothing pickable are pruned: disabled ancestors are
 * context for what hangs underneath them, an entirely dead branch is noise.
 */
export function ProductCategoryTreeSelect({
  value,
  onChange,
  businessFunctionId,
  rootCategoryId = null,
  disabled = false,
  action,
  triggerLabel,
}: ProductCategoryTreeSelectProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const tree = treeQuery.data

  const options = useMemo(() => {
    if (!tree || businessFunctionId === null) {
      return []
    }
    // Step 1: INV-1 — rows after the first never leave the resolved branch.
    const scoped = rootCategoryId === null ? tree : subtreeOf(tree, rootCategoryId)
    // Step 2: what this row may actually target, inside that scope.
    const pickableIds = pickableCategoryIdsFor(scoped, businessFunctionId)
    // Step 3: keep the pickable nodes and the ancestors that lead to them,
    // the latter listed as disabled context. D-3b: the value already saved on
    // the row survives the pruning and stays pickable even when it would no
    // longer qualify — a grandfathered row must not blank out on open.
    const kept = value === null ? pickableIds : new Set([...pickableIds, value])

    return flattenCategoryTree(pruneToPickable(scoped, kept), {
      pickableIds,
      keepIds: value === null ? undefined : [value],
    })
  }, [tree, rootCategoryId, businessFunctionId, value])

  return (
    <SearchableSelect
      value={value}
      onChange={(id) => onChange(id, tree ? categoryManagementMetaFor(tree, id) : null)}
      options={options}
      isPending={treeQuery.isPending}
      isError={treeQuery.isError}
      onRetry={() => void treeQuery.refetch()}
      disabled={disabled || businessFunctionId === null}
      action={action}
      labels={{
        placeholder: t('productLines.selectPlaceholder'),
        searchPlaceholder: t('productLines.productCategorySearch'),
        empty: t('productLines.selectEmpty'),
        noMatch: t('productLines.selectEmpty'),
        error: t('productLines.selectError'),
        retry: t('common.retry'),
        triggerLabel,
      }}
    />
  )
}
