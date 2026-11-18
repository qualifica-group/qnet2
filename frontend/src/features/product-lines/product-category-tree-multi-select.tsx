import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableMultiSelect, type SearchableMultiSelectOption } from '@/components/ui/searchable-multi-select'
import { pruneToPickable } from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { cardCategoryTree, selectableIdsUnderRoot } from '@/features/product-lines/category-tree-scope'

export interface ProductCategoryTreeMultiSelectProps {
  value: number[]
  onChange: (categoryIds: number[]) => void
  /** The root (or intermediate filter) whose selectable descendants are offered; disabled while null. */
  scopeCategoryId: number | null
  disabled?: boolean
  /** Accessible name of the trigger: a repeated row editor has no visible label of its own. */
  triggerLabel: string
}

/** Depth-first options with their parent link, a non-pickable node becoming a group header. */
function toOptions(
  nodes: ProductCategoryTreeNode[],
  pickableIds: ReadonlySet<number>,
  parentValue: string | null = null,
): SearchableMultiSelectOption[] {
  return nodes.flatMap((node) => {
    const value = String(node.id)
    const option: SearchableMultiSelectOption = {
      value,
      label: node.name,
      parentValue,
      selectable: pickableIds.has(node.id),
    }
    return [option, ...toOptions(node.children, pickableIds, value)]
  })
}

/**
 * The "categoria prodotto" step of a CARD row whose parent category is managed
 * in `multiple` mode (user request 2026-11-18): several categories of the same
 * parent are picked in ONE row instead of one row each, with "select all" and
 * a click on a group header picking its whole subtree. Same scoping as the
 * single `ProductCategoryTreeSelect`: the card tree, the selectable
 * descendants of the scope, and the values already held kept pickable (D-3b).
 */
export function ProductCategoryTreeMultiSelect({
  value,
  onChange,
  scopeCategoryId,
  disabled = false,
  triggerLabel,
}: ProductCategoryTreeMultiSelectProps) {
  const { t } = useTranslation()
  const tree = useProductCategoryTree().data

  const options = useMemo(() => {
    if (!tree || scopeCategoryId === null) {
      return []
    }
    // Step 1: the card tree, keeping the categories the row already holds.
    const visibleTree = cardCategoryTree(tree, value)
    // Step 2: pickable = the scope's selectable descendants plus the held values.
    const pickableIds = new Set([...selectableIdsUnderRoot(visibleTree, scopeCategoryId), ...value])
    // Step 3: drop dead branches, list the rest with their parents as headers.
    return toOptions(pruneToPickable(visibleTree, pickableIds), pickableIds)
  }, [tree, scopeCategoryId, value])

  const selectedValues = useMemo(() => value.map(String), [value])

  return (
    <SearchableMultiSelect
      className="min-h-9"
      options={options}
      value={selectedValues}
      onChange={(next) => onChange(next.map(Number))}
      disabled={disabled || scopeCategoryId === null || options.length === 0}
      aria-label={triggerLabel}
      labels={{
        placeholder: t('productLines.selectPlaceholder'),
        allSelected: t('productLines.allSubcategoriesSelected'),
        searchPlaceholder: t('productLines.productCategorySearch'),
        selectAll: t('productLines.selectAllSubcategories'),
        noMatch: t('productLines.selectEmpty'),
        clear: t('productLines.clearSubcategories'),
        count: (selected, total) => t('productLines.subcategoriesCount', { selected, total }),
        includeChildrenHint: t('productLines.includeChildrenHint'),
      }}
    />
  )
}
