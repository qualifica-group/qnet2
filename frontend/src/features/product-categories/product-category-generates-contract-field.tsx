import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FileSignature } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedContractGenerationFlag } from '@/features/product-categories/contract-generation-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryGeneratesContractFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "includes a contract" rule (spec 0091): when off, an offer of this
 * branch closing positively opens no contract and the deal never reaches the
 * Contratti module. Same root-owned inheritance as
 * `requires_quote`/`management_mode`/`single_quote_per_opportunity` (twin of
 * `ProductCategorySingleQuoteField`, same tree cache, zero extra requests);
 * the backend withholds the contract at the status transition.
 */
export function ProductCategoryGeneratesContractField({
  control,
  mode,
  parentId,
}: ProductCategoryGeneratesContractFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(
    () => resolveInheritedContractGenerationFlag(nodesById, parentId),
    [nodesById, parentId],
  )
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.generatesContract, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged && mode.type === 'edit' && mode.category.generates_contract_source_category
      ? {
          value: mode.category.generates_contract,
          sourceCategory: mode.category.generates_contract_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="generates_contract"
      inheritance={inheritance}
      icon={FileSignature}
      label={t('productCategories.form.generatesContract')}
      hint={t('productCategories.form.generatesContractInfo')}
      hintLabel={t('productCategories.form.generatesContractInfoLabel')}
      description={t('productCategories.form.generatesContractHint')}
      inheritedDescription={t('productCategories.form.generatesContractInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
