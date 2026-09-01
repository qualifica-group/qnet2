import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import type { LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { createProduct, productDetailQueryKey, updateProduct } from '@/features/products/api'
import { buildCreatePayload, buildUpdatePayload, normalizeDecimal } from '@/features/products/product-form-payload'
import {
  buildCreateProductSchema,
  buildUpdateProductSchema,
  type CreateProductFormValues,
} from '@/features/products/product-schema'
import type {
  ProductDetail,
  ProductDetailWithPermissions,
  ProductFormMode,
} from '@/features/products/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { useProductAttributeLayout } from '@/features/products/use-product-attribute-layout'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'

/** Server-side generic field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'description',
  'cost',
  'price',
  'category_id',
  'product_type',
  'vat_rate_id',
  'supplier_id',
  'unit_of_measure_id',
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
  /** Create-only: sequential code suggestion prefilled into the `code` default (spec 0065). */
  initialCode?: string
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
export function useProductForm({ mode, onSuccess, initialCode }: UseProductFormArgs) {
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

  // Spec 0062: the selected category's configured PRODUCT-context layout for
  // this form's own mode (create/edit are independent layouts, D3) — additive
  // to `productAttributes` above, never a replacement for it.
  const layoutFormMode: LayoutFormMode = isEdit ? 'edit' : 'create'
  const productLayoutQuery = useProductAttributeLayout(categoryId, layoutFormMode)
  const productLayout = productLayoutQuery.data ?? null

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
        code: product.code,
        name: product.name,
        description: product.description,
        cost: normalizeDecimal(product.cost),
        price: normalizeDecimal(product.price),
        category_id: product.category_id,
        product_type: product.product_type,
        vat_rate_id: product.vat_rate_id,
        supplier_id: product.supplier_id,
        unit_of_measure_id: product.unit_of_measure_id,
        custom_fields: customFields.defaultValues,
        attribute_values: seedAttributeValues(productAttributes, product.attribute_values ?? {}),
      }
    }
    return {
      code: initialCode ?? '',
      name: '',
      description: null,
      cost: null,
      price: null,
      category_id: null,
      product_type: DEFAULT_PRODUCT_TYPE,
      vat_rate_id: null,
      supplier_id: null,
      unit_of_measure_id: null,
      custom_fields: customFields.defaultValues,
      attribute_values: {},
    }
    // `productAttributes` is intentionally excluded: this seeds the form's
    // INITIAL state only (mount), the effect below keeps it in sync as the
    // category — and therefore the effective attribute set — changes later.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mode, customFields.defaultValues, initialCode])

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
        // Overlay the saved fields onto the cached entry instead of replacing
        // it: `saved` is a bare `ProductDetail` without the `permissions`
        // envelope sibling, and the detail page reads `permissions.resource`.
        // Replacing would strip `permissions` and crash on navigation. When
        // nothing is cached, leave it untouched — the page's
        // `invalidateQueries` refetches the full `ProductDetailWithPermissions`.
        queryClient.setQueryData<ProductDetailWithPermissions>(
          productDetailQueryKey(mode.product.id),
          (previous) => (previous ? { ...previous, ...saved } : previous),
        )
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
    productAttributesLoading: productAttributesQuery.isLoading || productLayoutQuery.isLoading,
    productLayout,
    layoutFormMode,
    /** Wire the category field's `onChange` to also call this, alongside `field.onChange` — see `categoryId` above. */
    onCategoryChange: setCategoryId,
  }
}
