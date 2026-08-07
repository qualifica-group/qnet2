import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FileText } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { resolveInheritedQuoteFlag } from '@/features/product-categories/requires-quote-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryRequiresQuoteFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "this category is quoted" rule. Reuses `useProductCategoryTree` — the
 * same cache the parent picker in this form already loads — so resolving the
 * inherited value costs no request.
 *
 * While the tree has not resolved yet, an edit-mode category whose `parent_id`
 * is still the loaded one falls back to the detail's own
 * `requires_quote_source_category` rather than flash an editable control; the
 * tree takes over as soon as it is available.
 */
export function ProductCategoryRequiresQuoteField({
  control,
  mode,
  parentId,
}: ProductCategoryRequiresQuoteFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(() => resolveInheritedQuoteFlag(nodesById, parentId), [nodesById, parentId])
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.requiresQuote, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged && mode.type === 'edit' && mode.category.requires_quote_source_category
      ? {
          value: mode.category.requires_quote,
          sourceCategory: mode.category.requires_quote_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="requires_quote"
      inheritance={inheritance}
      icon={FileText}
      label={t('productCategories.form.requiresQuote')}
      hint={t('productCategories.form.requiresQuoteInfo')}
      hintLabel={t('productCategories.form.requiresQuoteInfoLabel')}
      description={t('productCategories.form.requiresQuoteHint')}
      inheritedDescription={t('productCategories.form.requiresQuoteInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
