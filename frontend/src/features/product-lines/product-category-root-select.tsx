import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { activeCategoryTree } from '@/features/product-categories/active-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { isSingleRootBlocked } from '@/features/product-lines/category-tree-scope'

const EMPTY_IDS: readonly number[] = []

export interface ProductCategoryRootSelectProps {
  value: number | null
  onChange: (rootCategoryId: number) => void
  disabled?: boolean
  /** Another row of the card already holds a category: `single` roots are listed disabled (spec 0077 AC-047). */
  otherRowsFilled?: boolean
  /** Accessible name of the trigger — a repeated row editor has no visible label of its own. */
  triggerLabel: string
}

/**
 * The card row's FIRST step (spec 0132 D-1): every root category (a node with
 * no parent) of the same tree `ProductCategoryTreeSelect` reads — the top
 * level of `useProductCategoryTree`'s cached response IS the root list, so
 * this needs no request of its own. Flat, never indented: a root has no
 * parent to nest under. No quick-create here (unlike the category step):
 * root categories are structural, not something a card operator adds ad hoc.
 */
export function ProductCategoryRootSelect({
  value,
  onChange,
  disabled = false,
  otherRowsFilled = false,
  triggerLabel,
}: ProductCategoryRootSelectProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const tree = treeQuery.data

  const options = useMemo<SearchableSelectOption[]>(() => {
    if (!tree) {
      return []
    }
    return activeCategoryTree(tree, value === null ? EMPTY_IDS : [value]).map((root) => ({
      id: root.id,
      name: root.name,
      depth: 0,
      disabled: isSingleRootBlocked(root, otherRowsFilled),
    }))
  }, [tree, otherRowsFilled, value])

  return (
    <SearchableSelect
      value={value}
      onChange={onChange}
      options={options}
      isPending={treeQuery.isPending}
      isError={treeQuery.isError}
      onRetry={() => void treeQuery.refetch()}
      disabled={disabled}
      labels={{
        placeholder: t('productLines.selectPlaceholder'),
        searchPlaceholder: t('productLines.rootCategorySearch'),
        empty: t('productLines.selectEmpty'),
        noMatch: t('productLines.selectEmpty'),
        error: t('productLines.selectError'),
        retry: t('common.retry'),
        triggerLabel,
      }}
    />
  )
}
