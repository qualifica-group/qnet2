import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { ListChecks } from 'lucide-react'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedSimplifiedOfferLineFlag } from '@/features/product-categories/simplified-offer-line-inheritance'
import {
  ProductCategoryRootFlagField,
  type RootFlagInheritance,
} from '@/features/product-categories/product-category-root-flag-field'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategorySimplifiedOfferLineFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "Semplificazione riga offerta" rule (spec 0114): when on, Gestione
 * Richieste stops asking the operator to compile quantity/unit price/VAT rate
 * on the offer row — the system values them from the picked product. Same
 * root-owned inheritance as `requires_quote`/`management_mode`/
 * `single_quote_per_opportunity`/`generates_contract` (twin of
 * `ProductCategoryGeneratesContractField`, same tree cache, zero extra
 * requests); the backend congeals the values at write time regardless of what
 * the client sends (D-4). The Offerte module never reads this flag (D-3).
 */
export function ProductCategorySimplifiedOfferLineField({
  control,
  mode,
  parentId,
}: ProductCategorySimplifiedOfferLineFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const fromTree = useMemo(
    () => resolveInheritedSimplifiedOfferLineFlag(nodesById, parentId),
    [nodesById, parentId],
  )
  const candidateInheritance: RootFlagInheritance | null = fromTree
    ? { value: fromTree.simplifiedOfferLine, sourceCategory: fromTree.sourceCategory }
    : null

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: RootFlagInheritance | null =
    parentUnchanged && mode.type === 'edit' && mode.category.simplified_offer_line_source_category
      ? {
          value: mode.category.simplified_offer_line,
          sourceCategory: mode.category.simplified_offer_line_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  return (
    <ProductCategoryRootFlagField
      control={control}
      name="simplified_offer_line"
      inheritance={inheritance}
      icon={ListChecks}
      label={t('productCategories.form.simplifiedOfferLine')}
      hint={t('productCategories.form.simplifiedOfferLineInfo')}
      hintLabel={t('productCategories.form.simplifiedOfferLineInfoLabel')}
      description={t('productCategories.form.simplifiedOfferLineHint')}
      inheritedDescription={t('productCategories.form.simplifiedOfferLineInheritedHint', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
      inheritedBadge={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    />
  )
}
