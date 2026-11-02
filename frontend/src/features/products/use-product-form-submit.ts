import { useFormSubmitError } from '@/features/auth/use-form-submit-error'
import type { Path } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { createProduct, productDetailQueryKey, updateProduct } from '@/features/products/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import { editDefaults, type ProductFormState, type ProductFormValues } from '@/features/products/use-product-form'
import type { ProductDetail, ProductFormMode } from '@/features/products/types'

/** Server-side generic field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'description',
  'cost',
  'price',
  'category_id',
  'product_type',
  'usages',
  'vat_rate_id',
  'suppliers',
  'unit_of_measure_id',
  'product_typology_id',
  'cost_type_id',
] as const

/** Spec 0320: the 422 keys of each supplier row (`suppliers.N.supplier_id` / `.price` / `.cost`). */
function supplierErrorFields(count: number): Path<ProductFormValues>[] {
  return Array.from({ length: count }, (_, index) => [
    `suppliers.${index}.supplier_id` as Path<ProductFormValues>,
    `suppliers.${index}.price` as Path<ProductFormValues>,
    `suppliers.${index}.cost` as Path<ProductFormValues>,
  ]).flat()
}

/** Domain key of the module statistics (mirrors `PRODUCTS_DOMAIN` in `products-table.tsx`). */
const PRODUCTS_DOMAIN = 'products'

interface UseProductFormSubmitArgs {
  state: ProductFormState
  mode: ProductFormMode
  /** Create: the `next-code` suggestion prefilled into `code`, omitted on submit when untouched. */
  suggestedCode?: string
  /** Called after a successful create/update. */
  onSuccess: (product: ProductDetail) => void
}

/**
 * Owns the product create/update submit: the payload (only the CURRENT
 * category's attribute codes are ever sent), the partial PATCH, the cache
 * seed and the server 422 mapping. On the in-place detail (spec 0218) the
 * form is reset on the saved record, so the next row starts clean.
 */
export function useProductFormSubmit({ state, mode, suggestedCode, onSuccess }: UseProductFormSubmitArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PRODUCTS_DOMAIN)
  const [serverError, setServerError] = useFormSubmitError()
  const { form, productAttributes, customFieldErrorPaths } = state

  const onSubmit = async (values: ProductFormValues) => {
    setServerError(null)
    const attributeCodes = productAttributes.map((attribute) => attribute.code)
    const errorFields: Path<ProductFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...supplierErrorFields(values.suppliers.length),
      ...(customFieldErrorPaths as Path<ProductFormValues>[]),
      ...attributeCodes.map((code) => `attribute_values.${code}` as Path<ProductFormValues>),
    ]
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and seed the cached detail with
        // the full shape the server returns (permissions and attributes included).
        const saved = await updateProduct(mode.product.id, buildUpdatePayload(values, mode.product, attributeCodes))
        queryClient.setQueryData(productDetailQueryKey(mode.product.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved, saved.custom_fields ?? {}, productAttributes), { keepDirtyValues: false })
        toast.success(t('products.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      const created = await createProduct(buildCreatePayload(values, attributeCodes, suggestedCode))
      toast.success(t('products.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(apiErrorMessage(error, t('products.form.genericError'), i18n))
      }
    }
  }

  // Opening or closing an in-place editor starts from a clean slate.
  const clearServerError = () => setServerError(null)

  return { serverError, onSubmit, clearServerError }
}
