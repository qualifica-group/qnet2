import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  flattenCategoryTree,
  pruneToPickable,
} from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import {
  pickableCategoryIdsFor,
  selectableIdsUnderRoot,
} from '@/features/product-lines/category-tree-scope'

/**
 * How a row scopes its pickable categories (spec 0132): `'root'` — the card
 * contract — offers every `is_selectable` descendant of the chosen ROOT
 * category, with no business-function constraint at all (the function is
 * derived server-side). `'business_function'` is the pre-existing competence
 * contract (spec 0111 D-4, spec 0129 D-6/D-7), unchanged: pickable ids match
 * the row's own business function, optionally admitting containers.
 */
export type CategoryPickScope =
  | { kind: 'root'; rootCategoryId: number | null }
  | { kind: 'business_function'; businessFunctionId: number | null; includeContainers: boolean }

export interface ProductCategoryTreeSelectProps {
  value: number | null
  onChange: (categoryId: number) => void
  /** What scopes this row's pickable set — see {@link CategoryPickScope}. Disabled until its id resolves. */
  scope: CategoryPickScope
  disabled?: boolean
  /** Quick-create affordance rendered next to the trigger (spec 0028). */
  action?: ReactNode
  /** Accessible name of the trigger — a repeated row editor has no visible label of its own. */
  triggerLabel: string
}

/** The id the scope resolves on, or `null` while the row's first step is still unset. */
function scopeId(scope: CategoryPickScope): number | null {
  return scope.kind === 'root' ? scope.rootCategoryId : scope.businessFunctionId
}

/**
 * The product-line row's "categoria prodotto" picker, reading the structural
 * category TREE (user directive 2026-08-03) so the PARENT categories are
 * listed above the pickable ones, indented, exactly as the product form's
 * picker lists them. A category the row may not target — not `is_selectable`,
 * or outside the row's scope — is shown DISABLED, never hidden: it is the
 * branch its children hang from.
 *
 * This replaces the flat, server-paginated `for-select` list this select used
 * to read. The scoping the endpoint did is resolved client-side against the
 * same cached tree the product form and the category tree view already
 * share — see `category-tree-scope.ts`, which resolves the card's management
 * mode off the very same tree.
 *
 * Branches offering nothing pickable are pruned: disabled ancestors are
 * context for what hangs underneath them, an entirely dead branch is noise.
 */
export function ProductCategoryTreeSelect({
  value,
  onChange,
  scope,
  disabled = false,
  action,
  triggerLabel,
}: ProductCategoryTreeSelectProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const tree = treeQuery.data
  const scopeReady = scopeId(scope) !== null

  const options = useMemo(() => {
    if (!tree || !scopeReady) {
      return []
    }
    // Step 1: what this row may actually target, per the scope kind.
    const pickableIds =
      scope.kind === 'root'
        ? selectableIdsUnderRoot(tree, scope.rootCategoryId as number)
        : pickableCategoryIdsFor(tree, scope.businessFunctionId as number, {
            includeContainers: scope.includeContainers,
          })
    // Step 2: keep the pickable nodes and the ancestors that lead to them,
    // the latter listed as disabled context. D-3b: the value already saved on
    // the row survives the pruning and stays pickable even when it would no
    // longer qualify — a grandfathered row must not blank out on open.
    const kept = value === null ? pickableIds : new Set([...pickableIds, value])

    return flattenCategoryTree(pruneToPickable(tree, kept), {
      pickableIds,
      keepIds: value === null ? undefined : [value],
    })
  }, [tree, scope, scopeReady, value])

  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
      options={options}
      isPending={treeQuery.isPending}
      isError={treeQuery.isError}
      onRetry={() => void treeQuery.refetch()}
      disabled={disabled || !scopeReady}
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
