import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  flattenCategoryTree,
  pruneToPickable,
} from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { pickableCategoryIdsFor } from '@/features/product-lines/category-tree-scope'

export interface ProductCategoryTreeSelectProps {
  value: number | null
  onChange: (categoryId: number) => void
  /**
   * The row's business function: only the categories whose EFFECTIVE one
   * matches are pickable. `null` (no function chosen yet) offers nothing and
   * disables the control — the row's two steps stay in order.
   */
  businessFunctionId: number | null
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
 * to read. The scoping the endpoint did (effective business function) is
 * resolved client-side against the same cached tree the product form and the
 * category tree view already share — see `category-tree-scope.ts`, which
 * resolves the card's management mode off the very same tree.
 *
 * Branches offering nothing pickable are pruned: disabled ancestors are
 * context for what hangs underneath them, an entirely dead branch is noise.
 */
export function ProductCategoryTreeSelect({
  value,
  onChange,
  businessFunctionId,
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
    // Step 1: what this row may actually target. The whole tree is in scope:
    // spec 0077 rev.2 revoked INV-1, so a row is no longer confined to the
    // branch root the card resolved.
    const pickableIds = pickableCategoryIdsFor(tree, businessFunctionId)
    // Step 2: keep the pickable nodes and the ancestors that lead to them,
    // the latter listed as disabled context. D-3b: the value already saved on
    // the row survives the pruning and stays pickable even when it would no
    // longer qualify — a grandfathered row must not blank out on open.
    const kept = value === null ? pickableIds : new Set([...pickableIds, value])

    return flattenCategoryTree(pruneToPickable(tree, kept), {
      pickableIds,
      keepIds: value === null ? undefined : [value],
    })
  }, [tree, businessFunctionId, value])

  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
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
