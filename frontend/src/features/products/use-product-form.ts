import { useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { useEffectiveAttributes } from '@/features/product-categories/use-effective-attributes'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import { DEFAULT_PRODUCT_USAGES, normalizeDecimal } from '@/features/products/product-form-payload'
import { EMPTY_SUPPLIER_ROWS, rowsFromOptions } from '@/features/products/product-supplier-rows'
import {
  buildCreateProductSchema,
  buildUpdateProductSchema,
  type CreateProductFormValues,
} from '@/features/products/product-schema'
import type { ProductDetail, ProductFormMode } from '@/features/products/types'
import { useProductAttributeLayout } from '@/features/products/use-product-attribute-layout'

/** Default product type for a new product (SERVICE-only catalogue for now). */
const DEFAULT_PRODUCT_TYPE = 'SERVICE' as const

/** Stable empty default: hoisted so it never creates a fresh reference per render (frontend.md §10). */
const EMPTY_ATTRIBUTES: EffectiveAttribute[] = []

export type ProductFormValues = CreateProductFormValues

/** Seeds the dynamic `attribute_values` RHF slice: every current PRODUCT-context code, known value or `null` when unset. */
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

/** The persisted product as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(
  product: ProductDetail,
  customFields: Record<string, CustomFieldValue>,
  attributes: EffectiveAttribute[],
): ProductFormValues {
  return {
    code: product.code,
    name: product.name,
    description: product.description,
    cost: normalizeDecimal(product.cost),
    price: normalizeDecimal(product.price),
    category_id: product.category_id,
    product_type: product.product_type,
    usages: product.usages ?? DEFAULT_PRODUCT_USAGES,
    vat_rate_id: product.vat_rate_id,
    suppliers: rowsFromOptions(product.suppliers),
    unit_of_measure_id: product.unit_of_measure_id,
    product_typology_id: product.product_typology_id,
    cost_type_id: product.cost_type_id ?? null,
    custom_fields: customFields,
    attribute_values: seedAttributeValues(attributes, product.attribute_values ?? {}),
  }
}

function createDefaults(customFields: Record<string, CustomFieldValue>, initialCode: string | undefined): ProductFormValues {
  return {
    code: initialCode ?? '',
    name: '',
    description: null,
    cost: 0,
    price: 0,
    category_id: null,
    product_type: DEFAULT_PRODUCT_TYPE,
    usages: DEFAULT_PRODUCT_USAGES,
    vat_rate_id: null,
    suppliers: EMPTY_SUPPLIER_ROWS,
    unit_of_measure_id: null,
    product_typology_id: null,
    cost_type_id: null,
    custom_fields: customFields,
    attribute_values: {},
  }
}

interface UseProductFormArgs {
  mode: ProductFormMode
  /** Create-only: sequential code suggestion prefilled into the `code` default (spec 0065). */
  initialCode?: string
}

/**
 * Owns the RHF/Zod wiring of the product create form and of the in-place
 * detail (`useProductInlineEdit`, edit mode): custom fields (spec 0021) and
 * the spec 0061 dynamic `attribute_values` slice — the selected category's
 * PRODUCT-context effective attributes drive both validation and which RHF
 * paths exist. Submission lives in `useProductFormSubmit`.
 *
 * Edit mode IS the product detail (spec 0218): the persisted record can
 * change under the form, so `values` re-syncs it while `keepDirtyValues`
 * preserves the row still being edited.
 */
export function useProductForm({ mode, initialCode }: UseProductFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'

  // Tracked as its OWN state (the schema depends on it, so it cannot be read
  // back from the form it builds) and moved from the category control's
  // `onChange` — an event handler — or re-synced on the form's category when
  // a row opens or is cancelled (`syncCategory`).
  const [categoryId, setCategoryId] = useState<number | null>(mode.type === 'edit' ? mode.product.category_id : null)
  const productAttributesQuery = useEffectiveAttributes(categoryId, 'product')
  // The detail already carries its category's attributes: they stand in until
  // the lookup answers, so a row opened right away is validated and sent.
  const persistedAttributes = useMemo(
    () =>
      mode.type === 'edit'
        ? (mode.product.applicable_attributes ?? []).map((attribute) => toEffectiveAttribute(attribute, 'product'))
        : EMPTY_ATTRIBUTES,
    [mode],
  )
  const productAttributes =
    productAttributesQuery.data ??
    (mode.type === 'edit' && categoryId === mode.product.category_id ? persistedAttributes : EMPTY_ATTRIBUTES)

  // Spec 0062: the create rows follow the category's configured create-form
  // layout; the detail shows the persisted product's own view layout.
  const productLayoutQuery = useProductAttributeLayout(isEdit ? null : categoryId, 'create')

  const customFields = useCustomFieldsForm(
    'products',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.product.custom_fields } : { type: 'create' },
  )

  // The layout the form renders decides which sections are hidden, hence which
  // required attributes do not apply (spec 0288 D-4).
  const renderedLayout =
    mode.type === 'edit' ? (mode.product.attribute_layout ?? null) : (productLayoutQuery.data ?? null)

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateProductSchema(t, customFields.schema, productAttributes, renderedLayout)
        : buildCreateProductSchema(t, customFields.schema, productAttributes, renderedLayout),
    [isEdit, t, customFields.schema, productAttributes, renderedLayout],
  )

  const defaultValues = useMemo<ProductFormValues>(
    () =>
      mode.type === 'edit'
        ? editDefaults(mode.product, customFields.defaultValues, productAttributes)
        : createDefaults(customFields.defaultValues, initialCode),
    // `productAttributes` is intentionally excluded: a category switch inside
    // an open row must not re-seed the form; the effect below adds the codes
    // a new attribute set brings.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [mode, customFields.defaultValues, initialCode],
  )

  const form = useForm<ProductFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  // Adds every newly-appearing PRODUCT attribute code to the RHF state (so
  // its control has a defined, controlled value); a code that stops applying
  // (category switch) is left in place but harmless — the payload builders
  // only read the CURRENT attribute set.
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

  return {
    form,
    productAttributes,
    productAttributesLoading: productAttributesQuery.isLoading || productLayoutQuery.isLoading,
    /** The create rows' configured layout (`null` on the detail, which reads the product's own). */
    createLayout: productLayoutQuery.data ?? null,
    customFieldErrorPaths: customFields.errorPaths,
    /** Wire the category control's `onChange` to also call this, alongside `field.onChange`. */
    onCategoryChange: setCategoryId,
    /** Re-aligns the attribute set on the form's category (a row opened, cancelled or reset). */
    syncCategory: () => setCategoryId(form.getValues('category_id')),
  }
}

/** What `useProductForm` hands its callers. */
export type ProductFormState = ReturnType<typeof useProductForm>
