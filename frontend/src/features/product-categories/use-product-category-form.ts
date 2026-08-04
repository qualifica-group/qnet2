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
  MANAGER_LABEL_MIN_ROWS,
  padManagerLabelPositions,
  type CreateProductCategoryFormValues,
} from '@/features/product-categories/product-category-schema'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import type {
  ManagerLabels,
  ProductCategoryDetail,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'parent_id',
  'inherits_product_attributes',
  'inherits_opportunity_attributes',
  'description',
  'attributes',
  'business_function_id',
  'requires_quote',
  'is_selectable',
  'management_mode',
  'manager_labels',
  'inherits_manager_labels',
] as const

/** Empty form value: the reasonable-minimum blank rows a brand-new category opens with (spec 0080 A1). */
const EMPTY_MANAGER_LABELS_FORM: ManagerLabels = Object.fromEntries(
  padManagerLabelPositions([], MANAGER_LABEL_MIN_ROWS).map((position) => [String(position), '']),
)

/**
 * Seeds the dynamic rows the section opens with: the category's own
 * positions, padded with the smallest free ones up to the reasonable minimum
 * (spec 0080 A1) — never trimmed, so a category with more own positions than
 * the minimum still shows all of them. The payload builder strips blanks back
 * out before sending (AC-042).
 */
function toManagerLabelsFormValue(labels: ManagerLabels): ManagerLabels {
  const ownPositions = Object.keys(labels).map(Number)
  const rows = padManagerLabelPositions(ownPositions, MANAGER_LABEL_MIN_ROWS)
  return Object.fromEntries(rows.map((position) => [String(position), labels[String(position)] ?? '']))
}

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
        inherits_product_attributes: category.inherits_product_attributes,
        inherits_opportunity_attributes: category.inherits_opportunity_attributes,
        description: category.description,
        attributes: category.attributes.map((assignment) => ({
          attribute_id: assignment.attribute_id,
          context: assignment.context,
          is_required: assignment.is_required,
          sort_order: assignment.sort_order,
        })),
        business_function_id: category.business_function_id,
        requires_quote: category.requires_quote,
        is_selectable: category.is_selectable,
        management_mode: category.management_mode,
        manager_labels: toManagerLabelsFormValue(category.manager_labels),
        inherits_manager_labels: category.inherits_manager_labels,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      parent_id: mode.parentId,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      requires_quote: false,
      // Spec 0074: a new category is a usable destination unless the
      // operator explicitly turns it into a container.
      is_selectable: true,
      // Spec 0077 D-8: `multiple` is the behavior every existing root already
      // has; a new root starts from the same default.
      management_mode: 'multiple',
      manager_labels: EMPTY_MANAGER_LABELS_FORM,
      inherits_manager_labels: true,
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
