import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { CATEGORY_POPUP_WIDTH_CLASS } from '@/features/product-lines/category-popup-width'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { cardCategoryTree, isRootUsableIn, isSingleRootBlocked } from '@/features/product-lines/category-tree-scope'
import type { CategoryPickArea } from '@/features/product-categories/types'

export interface ProductCategoryRootSelectProps {
  value: number | null
  /**
   * The category the row holds under that root. Kept with its path even when
   * inactive: an inactive root that is not selectable on its own (a legacy
   * structural root) would otherwise lose its only child and vanish.
   */
  categoryId?: number | null
  onChange: (rootCategoryId: number) => void
  disabled?: boolean
  /** Another row of the card already holds a category: `single` roots are listed disabled (spec 0077 AC-047). */
  otherRowsFilled?: boolean
  /** Accessible name of the trigger — a repeated row editor has no visible label of its own. */
  triggerLabel: string
  /** The module the card belongs to (spec 0389): roots reserved to the other one are not listed, the saved one stays. */
  area?: CategoryPickArea
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
  categoryId = null,
  onChange,
  disabled = false,
  otherRowsFilled = false,
  triggerLabel,
  area,
}: ProductCategoryRootSelectProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const tree = treeQuery.data

  const options = useMemo<SearchableSelectOption[]>(() => {
    if (!tree) {
      return []
    }
    const keepIds = [value, categoryId].filter((id): id is number => id !== null)
    return cardCategoryTree(tree, keepIds)
      .filter((root) => root.id === value || isRootUsableIn(root, area))
      .map((root) => ({
        id: root.id,
        name: root.name,
        depth: 0,
        disabled: isSingleRootBlocked(root, otherRowsFilled),
      }))
  }, [tree, otherRowsFilled, value, categoryId, area])

  return (
    <SearchableSelect
      contentClassName={CATEGORY_POPUP_WIDTH_CLASS}
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
