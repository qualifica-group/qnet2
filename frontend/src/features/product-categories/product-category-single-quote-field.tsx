import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FileCheck2 } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { resolveInheritedSingleQuoteFlag } from '@/features/product-categories/single-quote-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategorySingleQuoteFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "one offer per opportunity" rule: when on, an opportunity covered by
 * this category accepts a single offer. Same root-owned inheritance as
 * `requires_quote`/`management_mode` (twin of
 * `ProductCategoryRequiresQuoteField`, same tree cache, zero extra requests);
 * the backend enforces the cap at offer creation.
 */
export function ProductCategorySingleQuoteField({
  control,
  mode,
  parentId,
}: ProductCategorySingleQuoteFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(
    () => resolveInheritedSingleQuoteFlag(nodesById, parentId),
    [nodesById, parentId],
  )
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.singleQuotePerOpportunity, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged &&
    mode.type === 'edit' &&
    mode.category.single_quote_per_opportunity_source_category
      ? {
          value: mode.category.single_quote_per_opportunity,
          sourceCategory: mode.category.single_quote_per_opportunity_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="single_quote_per_opportunity"
      inheritance={inheritance}
      icon={FileCheck2}
      label={t('productCategories.form.singleQuotePerOpportunity')}
      hint={t('productCategories.form.singleQuotePerOpportunityInfo')}
      hintLabel={t('productCategories.form.singleQuotePerOpportunityInfoLabel')}
      description={t('productCategories.form.singleQuotePerOpportunityHint')}
      inheritedDescription={t('productCategories.form.singleQuotePerOpportunityInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
