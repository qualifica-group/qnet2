import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { createProduct, updateProduct } from '@/features/products/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import {
  buildCreateProductSchema,
  buildUpdateProductSchema,
  type CreateProductFormValues,
} from '@/features/products/product-schema'
import type { ProductDetail, ProductFormMode } from '@/features/products/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'

/** Server-side generic field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'cost',
  'price',
  'category_id',
  'product_type',
  'vat_rate_id',
  'supplier_id',
] as const

/** Default product type for a new product (SERVICE-only catalogue for now). */
const DEFAULT_PRODUCT_TYPE = 'SERVICE' as const

/** Domain key of the module statistics (mirrors `PRODUCTS_DOMAIN` in `products-table.tsx`). */
const PRODUCTS_DOMAIN = 'products'

/** Stable empty default: hoisted so it never creates a fresh reference per render (frontend.md §10). */
const EMPTY_ATTRIBUTES: EffectiveAttribute[] = []

export type ProductFormValues = CreateProductFormValues

interface UseProductFormArgs {
  mode: ProductFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (product: ProductDetail) => void
}

/** Seeds/prunes the dynamic `attribute_values` RHF slice: every current PRODUCT-context code, known value or `null` when unset. */
function seedAttributeValues(
  attributes: EffectiveAttribute[],
  values: Record<string, unknown>,
): Record<string, CustomFieldValue> {
  const seeded: Record<string, CustomFieldValue> = {}
  for (const attribute of attributes) {
    seeded[attribute.code] = (values[attribute.code] as CustomFieldValue | undefined) ?? null
  }
  return seeded
}

/**
 * Owns every non-render concern of `ProductForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`). Also
 * owns the spec 0061 dynamic `attribute_values` slice: the selected
 * category's PRODUCT-context effective attributes drive both validation
 * (`buildCreateProductSchema`) and which RHF paths exist.
 */
export function useProductForm({ mode, onSuccess }: UseProductFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const invalidateStats = useInvalidateModuleStats(PRODUCTS_DOMAIN)
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Tracked as its OWN state (not derived from `form.watch`, which would need
  // `form` to already exist — circular, since `form` itself is built from a
  // schema that depends on this id) and updated straight from the category
  // field's `onChange` (`onCategoryChange` below), an event handler — never
  // from an effect (react-compiler forbids `setState` inside one) — so the
  // dynamic attribute schema/fields react to the user picking a category
  // mid-session, not just at mount.
  const [categoryId, setCategoryId] = useState<number | null>(
    mode.type === 'edit' ? mode.product.category_id : null,
  )
  const productAttributesQuery = useEffectiveAttributes(categoryId, 'product')
  const productAttributes = productAttributesQuery.data ?? EMPTY_ATTRIBUTES

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'products',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.product.custom_fields } : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProductSchema(t, customFields.schema, productAttributes)
        : buildCreateProductSchema(t, customFields.schema, productAttributes),
    [isEdit, t, customFields.schema, productAttributes],
  )

  const defaultValues = useMemo<ProductFormValues>(() => {
    if (mode.type === 'edit') {
      const { product } = mode
      return {
        name: product.name,
        description: product.description,
        cost: product.cost,
        price: product.price,
        category_id: product.category_id,
        product_type: product.product_type,
        vat_rate_id: product.vat_rate_id,
        supplier_id: product.supplier_id,
        custom_fields: customFields.defaultValues,
        attribute_values: seedAttributeValues(productAttributes, product.attribute_values ?? {}),
      }
    }
    return {
      name: '',
      description: null,
      cost: null,
      price: null,
      category_id: null,
      product_type: DEFAULT_PRODUCT_TYPE,
      vat_rate_id: null,
      supplier_id: null,
      custom_fields: customFields.defaultValues,
      attribute_values: {},
    }
    // `productAttributes` is intentionally excluded: this seeds the form's
    // INITIAL state only (mount), the effect below keeps it in sync as the
    // category — and therefore the effective attribute set — changes later.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode, customFields.defaultValues])

  const form = useForm<ProductFormValues>({ resolver: zodResolver(schema), defaultValues })

  // Adds every newly-appearing PRODUCT attribute code to the RHF state (so
  // its control has a defined, controlled value); a code that stops applying
  // (category switch) is left in place but harmless — `buildCreatePayload`/
  // `buildUpdatePayload` only read the CURRENT attribute set.
  useEffect(() => {
    const known = mode.type === 'edit' ? (mode.product.attribute_values ?? {}) : {}
    for (const attribute of productAttributes) {
      const path = `attribute_values.${attribute.code}` as Path<ProductFormValues>
      if (form.getValues(path) === undefined) {
        form.setValue(path, (known[attribute.code] as CustomFieldValue | undefined) ?? null, {
          shouldValidate: false,
          shouldDirty: false,
        })
      }
    }
  }, [productAttributes, form, mode])

  const onSubmit = async (values: ProductFormValues) => {
    setServerError(null)
    const attributeCodes = productAttributes.map((attribute) => attribute.code)
    const errorFields: Path<ProductFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<ProductFormValues>[]),
      ...attributeCodes.map((code) => `attribute_values.${code}` as Path<ProductFormValues>),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateProduct(
          mode.product.id,
          buildUpdatePayload(values, mode.product, attributeCodes),
        )
        queryClient.setQueryData(['products', 'detail', mode.product.id], saved)
        toast.success(t('products.form.updated'))
        invalidateStats()
        onSuccess(saved)
        return
      }

      const created = await createProduct(buildCreatePayload(values, attributeCodes))
      toast.success(t('products.form.created'))
      invalidateStats()
      onSuccess(created)
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, errorFields)
      if (!handled) {
        setServerError(t('products.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
    productAttributes,
    productAttributesLoading: productAttributesQuery.isLoading,
    /** Wire the category field's `onChange` to also call this, alongside `field.onChange` — see `categoryId` above. */
    onCategoryChange: setCategoryId,
  }
}
