import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Layers } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedMultiQuoteWorkOrderFlag } from '@/features/product-categories/multi-quote-work-order-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryMultiQuoteWorkOrderFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "multi-offer work order" rule (spec 0267): when on, the Contratti table
 * may schedule one work order gathering lines of several won contracts of this
 * branch. Same root-owned inheritance as `generates_contract` (same tree
 * cache, zero extra requests); the backend enforces it at scheduling.
 */
export function ProductCategoryMultiQuoteWorkOrderField({
  control,
  mode,
  parentId,
}: ProductCategoryMultiQuoteWorkOrderFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const [contract, multi] = useWatch({ control, name: ['generates_contract', 'multi_quote_work_order'] })

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(
    () => resolveInheritedMultiQuoteWorkOrderFlag(nodesById, parentId),
    [nodesById, parentId],
  )
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.multiQuoteWorkOrder, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged && mode.type === 'edit' && mode.category.multi_quote_work_order_source_category
      ? {
          value: mode.category.multi_quote_work_order,
          sourceCategory: mode.category.multi_quote_work_order_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="multi_quote_work_order"
      inheritance={inheritance}
      lockedReason={
        !contract && !multi ? t('productCategories.form.multiQuoteWorkOrderNeedsContract') : null
      }
      icon={Layers}
      label={t('productCategories.form.multiQuoteWorkOrder')}
      hint={t('productCategories.form.multiQuoteWorkOrderInfo')}
      hintLabel={t('productCategories.form.multiQuoteWorkOrderInfoLabel')}
      description={t('productCategories.form.multiQuoteWorkOrderHint')}
      inheritedDescription={t('productCategories.form.multiQuoteWorkOrderInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
