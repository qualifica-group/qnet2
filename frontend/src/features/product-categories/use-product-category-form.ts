import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createProductCategory, updateProductCategory } from '@/features/product-categories/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/product-categories/product-category-form-payload'
import {
  buildCreateProductCategorySchema,
  buildUpdateProductCategorySchema,
  type CreateProductCategoryFormValues,
} from '@/features/product-categories/product-category-schema'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import type {
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'parent_id',
  'inherits_attributes',
  'description',
  'attributes',
  'business_function_id',
] as const

export type ProductCategoryFormValues = CreateProductCategoryFormValues

interface UseProductCategoryFormArgs {
  mode: ProductCategoryFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (category: ProductCategoryDetail) => void
}

/**
 * Owns every non-render concern of `ProductCategoryForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useProductCategoryForm({ mode, onSuccess }: UseProductCategoryFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'product-categories',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.category.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProductCategorySchema(t, customFields.schema)
        : buildCreateProductCategorySchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ProductCategoryFormValues>(() => {
    if (mode.type === 'edit') {
      const { category } = mode
      return {
        name: category.name,
        parent_id: category.parent_id,
        inherits_attributes: category.inherits_attributes,
        description: category.description,
        attributes: category.attributes.map((assignment) => ({
          attribute_id: assignment.attribute_id,
          context: assignment.context,
          is_required: assignment.is_required,
          sort_order: assignment.sort_order,
        })),
        business_function_id: category.business_function_id,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      parent_id: mode.parentId,
      inherits_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  const form = useForm<ProductCategoryFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: ProductCategoryFormValues) => {
    setServerError(null)
    const errorFields: Path<ProductCategoryFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<ProductCategoryFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateProductCategory(
          mode.category.id,
          buildUpdatePayload(values, mode.category),
        )
        queryClient.setQueryData(productCategoryKeys.detail(mode.category.id), saved)
        queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
        toast.success(t('productCategories.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createProductCategory(buildCreatePayload(values))
      queryClient.invalidateQueries({ queryKey: productCategoryKeys.tree })
      toast.success(t('productCategories.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('productCategories.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
