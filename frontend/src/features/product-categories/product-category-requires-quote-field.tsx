import { useEffect, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedQuoteFlag,
  type InheritedQuoteFlag,
} from '@/features/product-categories/requires-quote-inheritance'
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
 * The "this category is quoted" switch. The flag belongs to the branch ROOT:
 * as soon as a parent is SELECTED (not necessarily the saved one) the control
 * turns read-only and displays the root's value, naming the root it comes
 * from. Reuses `useProductCategoryTree` — the same cache the parent picker in
 * this form already loads — so resolving the inherited value costs no request.
 *
 * While the tree has not resolved yet, an edit-mode category whose `parent_id`
 * is still the loaded one falls back to the detail's own
 * `requires_quote_source_category` rather than flash an editable control;
 * the tree takes over as soon as it is available.
 */
export function ProductCategoryRequiresQuoteField({
  control,
  mode,
  parentId,
}: ProductCategoryRequiresQuoteFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const { field } = useController({ control, name: 'requires_quote' })

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const candidateInheritance = useMemo(
    () => resolveInheritedQuoteFlag(nodesById, parentId),
    [nodesById, parentId],
  )

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: InheritedQuoteFlag | null =
    parentUnchanged && mode.type === 'edit' && mode.category.requires_quote_source_category
      ? {
          requiresQuote: mode.category.requires_quote,
          sourceCategory: mode.category.requires_quote_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback
  const inherited = inheritance !== null

  // Keep the RHF value on what will actually be persisted once a parent makes
  // the flag inherited: the payload builders drop it for a non-root category,
  // but the control must not keep showing a value the save would discard.
  useEffect(() => {
    if (inheritance !== null && field.value !== inheritance.requiresQuote) {
      field.onChange(inheritance.requiresQuote)
    }
  }, [inheritance, field])

  return (
    <MetaField
      control={control}
      name="requires_quote"
      metaKey="requires_quote"
      label={t('productCategories.form.requiresQuote')}
      description={
        <FormDescription>
          {inheritance
            ? t('productCategories.form.requiresQuoteInheritedHint', {
                category: inheritance.sourceCategory.name,
              })
            : t('productCategories.form.requiresQuoteHint')}
        </FormDescription>
      }
    >
      {({ disabled }) => (
        <FormControl>
          <Switch
            checked={inheritance ? inheritance.requiresQuote : field.value}
            onCheckedChange={field.onChange}
            disabled={disabled || inherited}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
