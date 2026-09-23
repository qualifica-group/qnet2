import { useEffect, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { FormControl, FormDescription } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import {
  indexCategoryTree,
  resolveInheritedBusinessFunction,
  type InheritedBusinessFunction,
} from '@/features/product-categories/business-function-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryBusinessFunctionFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The category's business function picker (spec 0023): an `AsyncPaginatedSelect`
 * on the existing `business-functions/for-select` endpoint, disabled and
 * showing the inherited value + source category whenever the SELECTED parent
 * (not necessarily the saved one) carries one — transitively, walking the
 * cached category tree (spec 0023 AC-018/AC-019). Reuses `useProductCategoryTree`
 * (same cache the parent picker already reads), no extra request.
 *
 * While the tree hasn't resolved yet, an edit-mode category whose `parent_id`
 * is still the loaded one falls back to the detail's own precomputed
 * `effective_business_function` rather than block on a loading gap; the tree
 * takes over as soon as it's available (it always is in practice, since the
 * parent picker in the same form already forces it to load).
 */
export function ProductCategoryBusinessFunctionField({
  control,
  mode,
  parentId,
}: ProductCategoryBusinessFunctionFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const { field } = useController({ control, name: 'business_function_id' })
  const { quickCreated, renderAction } = useQuickCreateAction(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE)

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const candidateInheritance = useMemo(
    () => resolveInheritedBusinessFunction(nodesById, parentId),
    [nodesById, parentId],
  )

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: InheritedBusinessFunction | null =
    parentUnchanged &&
    mode.type === 'edit' &&
    mode.category.effective_business_function?.inherited &&
    mode.category.effective_business_function.source_category
      ? {
          businessFunctionId: mode.category.effective_business_function.id,
          sourceCategory: mode.category.effective_business_function.source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback
  const inherited = inheritance !== null

  // A value picked while the field was enabled must not leak into the
  // payload if a LATER parent change makes the category inherit (AC-015):
  // the picker's `value` prop already displays the candidate inherited
  // function instead of `field.value`, but the underlying RHF value must
  // also clear so `buildUpdatePayload`'s diff never sees a stale selection.
  useEffect(() => {
    if (inherited && field.value !== null) {
      field.onChange(null)
    }
  }, [inherited, field])

  // Known label for the inherited value, avoiding an extra hydration round
  // trip when it happens to be the same function already reported by the
  // loaded detail; otherwise `AsyncPaginatedSelect` hydrates the label itself
  // from `business-functions/for-select` (its existing, generic mechanism).
  const knownInheritedItem: ForSelectItem | null =
    inheritance &&
    mode.type === 'edit' &&
    mode.category.effective_business_function?.id === inheritance.businessFunctionId
      ? { id: inheritance.businessFunctionId, label: mode.category.effective_business_function.name }
      : null

  const ownItem: ForSelectItem | null = useMemo(() => {
    const loaded = mode.type === 'edit' ? mode.category : mode.type === 'duplicate' ? mode.source : null
    if (!loaded?.business_function) {
      return null
    }
    return { id: loaded.business_function.id, label: loaded.business_function.name }
  }, [mode])

  return (
    <MetaField
      control={control}
      name="business_function_id"
      metaKey="business_function_id"
      label={t('productCategories.form.businessFunction')}
      description={
        inheritance ? (
          <FormDescription>
            {t('productCategories.form.businessFunctionInheritedHint', {
              category: inheritance.sourceCategory.name,
            })}
          </FormDescription>
        ) : undefined
      }
    >
      {({ disabled }) => {
        const isDisabled = disabled || inherited
        const quickCreatedMatch = quickCreated.find((ref) => ref.id === field.value) ?? null
        return (
          <FormControl>
            <AsyncPaginatedSelect
              resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
              value={inherited ? (inheritance?.businessFunctionId ?? null) : field.value}
              onChange={field.onChange}
              selectedItem={
                inherited
                  ? knownInheritedItem
                  : (quickCreatedMatch && { id: quickCreatedMatch.id, label: quickCreatedMatch.name }) ??
                    ownItem
              }
              disabled={isDisabled}
              labels={{
                placeholder: t('productCategories.form.businessFunctionPlaceholder'),
                searchPlaceholder: t('productCategories.form.businessFunctionSearch'),
                empty: t('productCategories.form.businessFunctionEmpty'),
                error: t('productCategories.form.businessFunctionError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('productCategories.form.businessFunction'),
                retry: t('common.retry'),
              }}
              action={renderAction((ref) => field.onChange(ref.id), isDisabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}
