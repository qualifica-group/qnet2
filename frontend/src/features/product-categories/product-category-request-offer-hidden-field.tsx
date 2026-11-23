import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { EyeOff } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { resolveInheritedRequestOfferHiddenFlag } from '@/features/product-categories/request-offer-hidden-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryRequestOfferHiddenFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "Nascondi offerta in Gestione Richieste" rule (spec 0414): when on, the
 * request forms of this branch drop the offer section. Same root-owned
 * inheritance as `multiple_open_opportunities` (same tree cache, zero extra
 * requests). A branch not used in Gestione Richieste may still carry it: it
 * simply has no effect there, so the switch is never locked.
 */
export function ProductCategoryRequestOfferHiddenField({
  control,
  mode,
  parentId,
}: ProductCategoryRequestOfferHiddenFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const usageArea = useWatch({ control, name: 'usage_area' })

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(() => resolveInheritedRequestOfferHiddenFlag(nodesById, parentId), [nodesById, parentId])
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.requestOfferHidden, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged && mode.type === 'edit' && mode.category.request_offer_hidden_source_category
      ? {
          value: mode.category.request_offer_hidden,
          sourceCategory: mode.category.request_offer_hidden_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="request_offer_hidden"
      inheritance={inheritance}
      icon={EyeOff}
      warning={usageArea === 'opportunities' ? t('productCategories.form.simplifiedOfferLineNeedsRequests') : null}
      label={t('productCategories.form.requestOfferHidden')}
      hint={t('productCategories.form.requestOfferHiddenInfo')}
      hintLabel={t('productCategories.form.requestOfferHiddenInfoLabel')}
      description={t('productCategories.form.requestOfferHiddenHint')}
      inheritedDescription={t('productCategories.form.requestOfferHiddenInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
