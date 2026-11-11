import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { CopyPlus } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedMultipleOpenOpportunitiesFlag } from '@/features/product-categories/multiple-open-opportunities-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryMultipleOpenOpportunitiesFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "several open opportunities per anagrafica" rule: when on, an open
 * opportunity of this branch no longer stops the anagrafica from opening
 * another one, while the management mode (and so the offer rows) stays as it
 * is. Same root-owned inheritance as `single_quote_per_opportunity` (same tree
 * cache, zero extra requests); the backend enforces it at opportunity creation.
 */
export function ProductCategoryMultipleOpenOpportunitiesField({
  control,
  mode,
  parentId,
}: ProductCategoryMultipleOpenOpportunitiesFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const [multipleOpen, managementMode, usageArea] = useWatch({
    control,
    name: ['multiple_open_opportunities', 'management_mode', 'usage_area'],
  })
  // Gestione Richieste opens one opportunity per request: with the rule off a
  // returning client is refused there, which the single-mode and requests-only
  // branches (the ones worked in Gestione Richieste) must not hit unawares.
  const blocksRequests = !multipleOpen && (managementMode === 'single' || usageArea === 'requests')

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(
    () => resolveInheritedMultipleOpenOpportunitiesFlag(nodesById, parentId),
    [nodesById, parentId],
  )
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.multipleOpenOpportunities, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged && mode.type === 'edit' && mode.category.multiple_open_opportunities_source_category
      ? {
          value: mode.category.multiple_open_opportunities,
          sourceCategory: mode.category.multiple_open_opportunities_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="multiple_open_opportunities"
      inheritance={inheritance}
      icon={CopyPlus}
      warning={blocksRequests ? t('productCategories.form.multipleOpenOpportunitiesOffWarning') : null}
      label={t('productCategories.form.multipleOpenOpportunities')}
      hint={t('productCategories.form.multipleOpenOpportunitiesInfo')}
      hintLabel={t('productCategories.form.multipleOpenOpportunitiesInfoLabel')}
      description={t('productCategories.form.multipleOpenOpportunitiesHint')}
      inheritedDescription={t('productCategories.form.multipleOpenOpportunitiesInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
