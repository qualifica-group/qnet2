import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { activeCategoryTree } from '@/features/product-categories/active-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { filterCategoriesUnderRoot } from '@/features/product-lines/category-tree-scope'

const EMPTY_IDS: readonly number[] = []

export interface ProductCategoryFilterSelectProps {
  rootCategoryId: number
  value: number | null
  onChange: (filterCategoryId: number) => void
  disabled?: boolean
  /** Rendered next to the trigger (the row's "remove filter" button), like the category select's quick-create. */
  action?: ReactNode
  /** Accessible name of the trigger — a repeated row editor has no visible label of its own. */
  triggerLabel: string
}

/**
 * The card row's OPTIONAL middle step (user directive 2026-10-08), shown only
 * once the operator adds it: the root's grouping children (e.g. "ISO" under
 * "Consulenza"), off the same cached tree as the other two selects. Flat like
 * `ProductCategoryRootSelect`: every option sits one level under the root.
 */
export function ProductCategoryFilterSelect({
  rootCategoryId,
  value,
  onChange,
  disabled = false,
  action,
  triggerLabel,
}: ProductCategoryFilterSelectProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const tree = treeQuery.data

  const options = useMemo<SearchableSelectOption[]>(
    () =>
      tree === undefined
        ? []
        : filterCategoriesUnderRoot(activeCategoryTree(tree, value === null ? EMPTY_IDS : [value]), rootCategoryId).map((node) => ({ id: node.id, name: node.name, depth: 0 })),
    [tree, rootCategoryId, value],
  )

  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
      options={options}
      isPending={treeQuery.isPending}
      isError={treeQuery.isError}
      onRetry={() => void treeQuery.refetch()}
      disabled={disabled}
      action={action}
      labels={{
        placeholder: t('productLines.filterPlaceholder'),
        searchPlaceholder: t('productLines.filterSearch'),
        empty: t('productLines.selectEmpty'),
        noMatch: t('productLines.selectEmpty'),
        error: t('productLines.selectError'),
        retry: t('common.retry'),
        triggerLabel,
      }}
    />
  )
}
