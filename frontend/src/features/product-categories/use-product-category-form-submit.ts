import { useState } from 'react'
import type { Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProductCategory, updateProductCategory } from '@/features/product-categories/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/product-categories/product-category-form-payload'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import {
  editDefaults,
  type ProductCategoryFormValues,
} from '@/features/product-categories/use-product-category-form'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'parent_id',
  'inherits_product_attributes',
  'inherits_quote_attributes',
  'inherits_work_order_attributes',
  'description',
  'attributes',
  'business_function_id',
  'requires_quote',
  'is_selectable',
  'is_active',
  'is_reportable',
  'report_columns',
  'management_mode',
  'single_quote_per_opportunity',
  'generates_contract',
  'multi_quote_work_order',
  'simplified_offer_line',
  'simplified_offer_line_override',
  'manager_labels',
  'inherits_manager_labels',
] as const

interface UseProductCategoryFormSubmitArgs {
  form: UseFormReturn<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** The custom fields' 422 paths (spec 0021), from `useProductCategoryForm`. */
  customFieldErrorPaths: string[]
  /** Called after a successful create/update so the caller can navigate or refresh. */
  onSuccess: (category: ProductCategoryDetail) => void
}

/**
 * Owns the category create/update submit: the payload (duplicate goes through
 * the create path), the partial PATCH, the cache seed and the server 422
 * mapping. On the in-place detail (spec 0229) the form is reset on the saved
 * record.
 */
export function useProductCategoryFormSubmit({
  form,
  mode,
  customFieldErrorPaths,
  onSuccess,
}: UseProductCategoryFormSubmitArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const onSubmit = async (values: ProductCategoryFormValues) => {
    setServerError(null)
    const errorFields: Path<ProductCategoryFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFieldErrorPaths as Path<ProductCategoryFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and seed the cached detail with
        // the full shape the server returns (permissions included).
        const saved = await updateProductCategory(
          mode.category.id,
          buildUpdatePayload(values, mode.category),
        )
        queryClient.setQueryData(productCategoryKeys.detail(mode.category.id), saved)
        void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved, saved.custom_fields ?? {}), { keepDirtyValues: false })
        toast.success(t('productCategories.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createProductCategory({
        ...buildCreatePayload(values),
        // The server copies the source's own attribute layouts onto the copy.
        ...(mode.type === 'duplicate' ? { layout_source_id: mode.source.id } : {}),
      })
      void queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
      toast.success(t('productCategories.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('productCategories.form.genericError'))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearServerError = () => setServerError(null)

  return { serverError, onSubmit, clearServerError }
}
